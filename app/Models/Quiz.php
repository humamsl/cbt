<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Quiz extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_upto' => 'datetime',
        'is_published' => 'boolean',
        'randomize' => 'boolean',
        'randomize_options' => 'boolean',
        'show_score' => 'boolean',
        'require_session_token' => 'boolean',
        'protection_enabled' => 'boolean',
        'settings' => 'array',
        'target_tingkat' => 'array',
    ];

    public function mapel() { return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id'); }
    public function rombel() { return $this->belongsTo(RombonganBelajar::class, 'rombongan_belajar_id'); }

    /** Multi-rombel target via pivot quiz_rombongan_belajar */
    public function rombelTargets()
    {
        return $this->belongsToMany(RombonganBelajar::class, 'quiz_rombongan_belajar');
    }

    /** Target per siswa (mode 'per_siswa') via pivot quiz_siswa di Data Center */
    public function siswaTargets()
    {
        return $this->belongsToMany(Siswa::class, 'quiz_siswa');
    }

    public function tahunAjaran() { return $this->belongsTo(TahunAjaran::class); }
    public function creator() { return $this->belongsTo(Guru::class, 'created_by_guru_id'); }
    public function sessionToken() { return $this->belongsTo(SessionToken::class); }

    public function questions() { return $this->hasMany(QuizQuestion::class)->orderBy('order'); }
    public function attempts() { return $this->hasMany(QuizAttempt::class); }

    /**
     * Scope: hanya quiz yang MEMANG ditargetkan ke siswa ini (rombel/tingkat
     * di tahun ajaran aktif).
     *
     * PENTING: target rombel TIDAK boleh dicek pakai whereHas('rombelTargets')
     * dari query Quiz. Quiz hidup di database cbt sedangkan pivot
     * `quiz_rombongan_belajar` + `rombongan_belajar` sudah pindah ke database
     * Data Center — whereHas menempelkan subquery TANPA prefix database pada
     * koneksi cbt, sehingga yang terbaca justru tabel lokal cbt yang basi.
     * Akibatnya ujian baru tidak muncul (pivot barunya hanya ada di Data
     * Center) dan siswa bisa kecocokan dengan baris pivot lama yang salah.
     * Solusinya: ambil dulu quiz_id dari pivot Data Center lewat koneksinya
     * sendiri, baru whereIn di query cbt.
     */
    public function scopeUntukSiswa($query, $siswa)
    {
        $rombelRows = SiswaRombel::where('siswa_id', $siswa->id)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->with('rombel:id,tingkat')->get();

        $rombelIds   = $rombelRows->pluck('rombongan_belajar_id')->filter()->unique()->values()->all();
        $tingkatList = $rombelRows->pluck('rombel.tingkat')->filter()->unique()->values()->all();

        $quizIdsPivot = empty($rombelIds) ? [] : DB::connection('mysql_datacenter')
            ->table('quiz_rombongan_belajar')
            ->whereIn('rombongan_belajar_id', $rombelIds)
            ->pluck('quiz_id')->unique()->values()->all();

        // Mode per_siswa → quiz yang menunjuk siswa ini langsung di pivot
        $quizIdsSiswa = DB::connection('mysql_datacenter')
            ->table('quiz_siswa')
            ->where('siswa_id', $siswa->id)
            ->pluck('quiz_id')->unique()->values()->all();

        return $query->where(function ($q) use ($rombelIds, $tingkatList, $quizIdsPivot, $quizIdsSiswa) {
            // Mode per_kelas → quiz ada di pivot Data Center ATAU legacy single rombel
            $q->where(function ($x) use ($rombelIds, $quizIdsPivot) {
                $x->where('target_mode', 'per_kelas')
                  ->where(function ($y) use ($rombelIds, $quizIdsPivot) {
                      $y->whereIn('quizzes.id', $quizIdsPivot ?: [0])
                        ->orWhereIn('rombongan_belajar_id', $rombelIds ?: [0]);
                  });
            })
            // Mode per_tingkat → salah satu tingkat siswa ada di kolom JSON.
            // Dicek sebagai int DAN string: data lama bisa tersimpan ["8"]
            // sedangkan whereJsonContains(8) tidak match "8" di MySQL.
            ->orWhere(function ($x) use ($tingkatList) {
                $x->where('target_mode', 'per_tingkat');
                if (empty($tingkatList)) {
                    $x->whereRaw('1=0');
                    return;
                }
                $x->where(function ($y) use ($tingkatList) {
                    foreach ($tingkatList as $tk) {
                        $y->orWhereJsonContains('target_tingkat', (int) $tk)
                          ->orWhereJsonContains('target_tingkat', (string) $tk);
                    }
                });
            })
            // Mode per_siswa → siswa ini dipilih langsung sebagai peserta
            ->orWhere(function ($x) use ($quizIdsSiswa) {
                $x->where('target_mode', 'per_siswa')
                  ->whereIn('quizzes.id', $quizIdsSiswa ?: [0]);
            });
        });
    }

    /** Apakah quiz ini memang ditargetkan ke siswa tsb (guard server-side). */
    public function ditargetkanUntukSiswa($siswa): bool
    {
        return static::untukSiswa($siswa)->whereKey($this->id)->exists();
    }

    /** Jadwal belum tiba → tombol mulai harus terkunci & start() ditolak. */
    public function getBelumDimulaiAttribute(): bool
    {
        return $this->valid_from && Carbon::now()->lt($this->valid_from);
    }

    /** Jadwal sudah lewat → tidak boleh mulai lagi. */
    public function getSudahBerakhirAttribute(): bool
    {
        return $this->valid_upto && Carbon::now()->gt($this->valid_upto);
    }

    public function getStatusAttribute(): string
    {
        if (! $this->is_published) return 'draft';
        $now = Carbon::now();
        if ($this->valid_from && $now->lt($this->valid_from)) return 'menunggu';
        if ($this->valid_upto && $now->gt($this->valid_upto)) return 'selesai';
        return 'berlangsung';
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'badge-muted',
            'menunggu' => 'badge-info',
            'berlangsung' => 'badge-success',
            'selesai' => 'badge-warning',
            default => 'badge-muted',
        };
    }
}
