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
