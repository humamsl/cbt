<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizAttempt extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_done' => 'boolean',
        'is_blocked' => 'boolean',
        'is_force_submitted' => 'boolean',
        'time_start' => 'datetime',
        'time_end' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function getStatusAttribute(): string
    {
        if ($this->is_blocked) return 'blokir';
        if ($this->is_done) return 'selesai';
        if ($this->time_start) return 'sedang';
        return 'belum';
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            'belum'  => 'badge-muted',
            'sedang' => 'badge-info',
            'blokir' => 'badge-danger',
            'selesai'=> 'badge-success',
        ][$this->status] ?? 'badge-muted';
    }

    public function quiz() { return $this->belongsTo(Quiz::class); }
    public function siswa() { return $this->belongsTo(Siswa::class); }
    public function answers() { return $this->hasMany(QuizAttemptAnswer::class); }
    public function violations() { return $this->hasMany(ExamViolation::class); }

    /**
     * Ringkas konsekuensi pelanggaran attempt ini untuk Monitoring Ujian:
     * apakah berujung diblokir, logout otomatis, nilai dipotong, atau
     * sekadar tercatat (mode "Peringatan Saja"). null kalau tidak ada
     * pelanggaran sama sekali. Butuh relasi `quiz` sudah di-load (lihat
     * MonitoringController::detail() yang men-set-relation manual).
     */
    public function getKonsekuensiPelanggaranAttribute(): ?array
    {
        if (($this->violation_count ?? 0) === 0) return null;

        if ($this->is_blocked) {
            return [
                'text' => 'Ujian diblokir',
                'detail' => $this->blocked_reason,
                'badge' => 'badge-danger',
            ];
        }

        if ($this->is_force_submitted) {
            return [
                'text' => 'Disubmit otomatis & keluar (logout)',
                'detail' => 'Jawaban disubmit paksa karena melebihi batas pelanggaran.',
                'badge' => 'badge-warning',
            ];
        }

        $quiz = $this->quiz;
        if ($quiz && $quiz->proteksi_mode === 'pengurangan_nilai' && $quiz->nilai_pengurangan > 0) {
            $potongan = $this->violation_count * (float) $quiz->nilai_pengurangan;
            return [
                'text' => ($this->is_done ? 'Nilai dipotong ' : 'Nilai akan dipotong ').$potongan.' poin',
                'detail' => "{$this->violation_count} pelanggaran × {$quiz->nilai_pengurangan} poin/pelanggaran.",
                'badge' => 'badge-warning',
            ];
        }

        return [
            'text' => 'Tercatat sebagai peringatan',
            'detail' => 'Tidak ada aksi otomatis untuk mode proteksi ujian ini.',
            'badge' => 'badge-muted',
        ];
    }

    /**
     * Nilai akhir skala 0–100 (otomatis "puluhan").
     *
     * Kolom `score` menyimpan POIN mentah (jumlah marks jawaban benar) —
     * kalau ditampilkan langsung, ujian 5 soal × 1 poin nilainya maksimal 5.
     * Accessor ini menormalkan ke skala 100: poin ÷ total poin quiz × 100.
     * Dihitung on-the-fly supaya attempt lama ikut benar tanpa migrasi data.
     */
    public function getNilaiAttribute(): ?float
    {
        if ($this->score === null) return null;

        $total = (float) ($this->quiz->total_marks ?? 0);
        if ($total <= 0) {
            // Quiz tanpa total poin (mis. soalnya sudah dihapus) → tampilkan poin apa adanya
            return round((float) $this->score, 1);
        }

        return round((float) $this->score / $total * 100, 1);
    }

    /**
     * Bangun peta status ujian (per quiz) untuk SATU siswa -- dipakai di
     * dashboard siswa & daftar ujian supaya tombol "Mulai Ujian" bisa
     * disesuaikan: terkunci kalau attempt sedang diblokir, "lanjutkan" kalau
     * sedang dikerjakan, dan tidak bisa diklik lagi kalau sudah selesai
     * (dicek terhadap Quiz::max_attempts di view, bukan di sini).
     *
     * PENTING soal 'attempt_blokir': JANGAN tambahkan syarat "! $a->is_done".
     * QuizAttempt yang diblokir (lewat UjianController::blockAndFinalize())
     * selalu ikut di-finalize() juga di baris kode yang sama -- jadi begitu
     * is_blocked jadi true, is_done JUGA ikut jadi true di attempt yang sama.
     * Kalau syarat "! is_done" dipasang, attempt yang sudah diblokir tidak
     * akan pernah cocok lagi di sini, sehingga tombol "Mulai Ujian" tidak
     * pernah terkunci meskipun siswanya sudah jelas diblokir.
     *
     * Return: [quiz_id => [
     *   'attempt_blokir'   => QuizAttempt|null,  // attempt aktif yang diblokir
     *   'attempt_sedang'   => QuizAttempt|null,  // attempt yang sedang dikerjakan (belum submit)
     *   'attempt_terbaru_selesai' => QuizAttempt|null, // attempt selesai paling baru (untuk link "Lihat Hasil")
     *   'jumlah_selesai'   => int,                // total attempt yang sudah selesai (untuk cek max_attempts)
     * ]]
     */
    public static function petaStatusUntukSiswa(iterable $quizIds, int $siswaId): array
    {
        $quizIds = collect($quizIds)->filter()->unique()->values();
        if ($quizIds->isEmpty()) return [];

        $attemptsPerQuiz = static::whereIn('quiz_id', $quizIds)
            ->where('siswa_id', $siswaId)
            ->orderByDesc('id')
            ->get()
            ->groupBy('quiz_id');

        $peta = [];
        foreach ($quizIds as $quizId) {
            $milik = $attemptsPerQuiz->get($quizId, collect());

            $peta[$quizId] = [
                'attempt_blokir' => $milik->first(fn ($a) => $a->is_blocked),
                'attempt_sedang' => $milik->first(fn ($a) => ! $a->is_done && ! $a->is_blocked && $a->time_start),
                'attempt_terbaru_selesai' => $milik->first(fn ($a) => $a->is_done && ! $a->is_blocked),
                'jumlah_selesai' => $milik->where('is_done', true)->where('is_blocked', false)->count(),
            ];
        }

        return $peta;
    }
}
