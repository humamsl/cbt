<?php

namespace App\Services\Ujian;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logika penilaian & finalisasi ujian — dipindah dari Cbt\UjianController
 * (web) supaya dipakai BERSAMA oleh alur web dan API aplikasi mobile.
 * Sengaja satu sumber kebenaran: kalau dua jalur ini sempat menyimpang,
 * nilai siswa bisa beda tergantung dari mana dia mengerjakan ujian --
 * masalah integritas, bukan sekadar bug tampilan.
 */
class ExamScoringService
{
    /**
     * Hitung pecahan nilai (0.0 - 1.0) yang didapat siswa untuk satu soal,
     * berdasarkan jenis soal.
     *
     * - Pilihan ganda / benar-salah   → cek question_option_id thd opsi is_correct
     * - PGK (multi-jawaban benar)     → proporsional: (opsi benar terpilih −
     *                                   opsi salah terpilih) / total opsi benar,
     *                                   minimal 0 (memilih SEMUA opsi tidak bisa
     *                                   dipakai buat "aman" dapat nilai penuh)
     * - Penjodohan                    → proporsional: pasangan yang dicocokkan
     *                                   benar / total pasangan
     * - Fill the blank                → bandingkan answer_text dengan correct_answer_text
     *                                   - default: case-INsensitive + trim spasi
     *                                   - kalau soal.case_sensitive = true → perlu sama persis huruf besar/kecil
     *                                   - multi-jawaban: pisah pakai "|" di correct_answer_text
     *                                     mis. "Jakarta|DKI Jakarta|Daerah Khusus Ibukota Jakarta"
     */
    public function answerScoreFraction($question, $ans): float
    {
        if (! $question) return 0.0;

        $typeSlug = strtolower((string) (optional($question->type)->slug ?? optional($question->type)->question_type ?? ''));

        // Fill-blank: perbandingan teks
        if ($typeSlug === 'fill-blank' || $typeSlug === 'fill_blank' || str_contains($typeSlug, 'fill')) {
            $student = (string) ($ans->answer_text ?? '');
            $key     = (string) ($question->correct_answer_text ?? '');

            // Daftar jawaban benar (pisahkan dengan | untuk multi-jawaban)
            $accepted = array_filter(array_map('trim', explode('|', $key)), fn ($v) => $v !== '');
            if (empty($accepted)) return 0.0;

            $caseSensitive = (bool) ($question->case_sensitive ?? false);
            $normalize = fn (string $s) => $caseSensitive
                ? trim($s)
                : mb_strtolower(trim($s));

            $studentNorm = $normalize($student);
            foreach ($accepted as $a) {
                if ($normalize($a) === $studentNorm) return 1.0;
            }
            return 0.0;
        }

        // PGK: nilai proporsional thd jumlah opsi benar yang berhasil dipilih,
        // dikurangi opsi salah yang ikut dipilih -- supaya mencentang semua
        // opsi bukan strategi aman.
        if ($typeSlug === 'pgk') {
            $correctIds = $question->options->where('is_correct', true)->pluck('id');
            $totalCorrect = $correctIds->count();
            if ($totalCorrect === 0) return 0.0;

            $selectedIds = collect($ans->selectedOptionIds());
            $correctSelected = $selectedIds->intersect($correctIds)->count();
            $wrongSelected = $selectedIds->diff($correctIds)->count();

            return max(0.0, ($correctSelected - $wrongSelected) / $totalCorrect);
        }

        // Penjodohan: nilai proporsional thd jumlah pasangan kiri-kanan yang
        // dicocokkan dengan benar (dibandingkan lewat pair_group yang sama).
        if ($typeSlug === 'penjodohan') {
            $leftOptions = $question->options->where('is_left_side', true);
            $totalPairs = $leftOptions->count();
            if ($totalPairs === 0) return 0.0;

            $rightByPairGroup = $question->options->where('is_left_side', false)->keyBy('pair_group');
            $studentPairs = $ans->matchPairs();

            $correctMatches = 0;
            foreach ($leftOptions as $left) {
                $chosenRightId = $studentPairs[$left->id] ?? null;
                $expectedRight = $rightByPairGroup[$left->pair_group] ?? null;
                if ($chosenRightId && $expectedRight && $chosenRightId === (int) $expectedRight->id) {
                    $correctMatches++;
                }
            }
            return $correctMatches / $totalPairs;
        }

        // PG / Benar-salah → cek question_option_id terhadap opsi is_correct
        $correctOption = $question->options->firstWhere('is_correct', true);
        return ($correctOption && $correctOption->id === $ans->question_option_id) ? 1.0 : 0.0;
    }

    /**
     * Hitung jumlah soal WAJIB (yang punya cara untuk dijawab, lihat
     * QuizQuestion::isAnswerable()) yang masih kosong pada attempt ini.
     * Dipakai submit() untuk menegakkan aturan "siswa harus menjawab semua
     * soal dulu baru bisa mengirim" -- TANPA ikut menghitung soal yang
     * memang tidak mungkin dijawab (soal terhapus, atau PG/PGK/Penjodohan
     * tanpa opsi sama sekali), supaya satu soal bermasalah tidak menyandera
     * siswa yang sudah menjawab semua yang bisa dia jawab. Fill-blank SELALU
     * ikut dihitung wajib di sini walau guru belum mengisi kunci jawabannya.
     */
    public function unansweredRequiredCount(Quiz $quiz, QuizAttempt $attempt): int
    {
        $quiz->loadMissing('questions.question.options');
        $answered = $attempt->answers()
            ->whereIn('quiz_question_id', $quiz->questions->pluck('id'))
            ->get()->keyBy('quiz_question_id');

        $empty = 0;
        foreach ($quiz->questions as $qq) {
            if (! $qq->isAnswerable()) continue;

            $ans = $answered->get($qq->id);
            $belumDijawab = ! $ans || (
                ! $ans->question_option_id
                && empty($ans->answer_json)
                && ! filled($ans->answer_text)
            );
            if ($belumDijawab) $empty++;
        }
        return $empty;
    }

    public function blockAndFinalize(Quiz $quiz, QuizAttempt $attempt, string $reason): void
    {
        DB::transaction(function () use ($quiz, $attempt, $reason) {
            $attempt->update([
                'is_blocked' => true,
                'blocked_at' => now(),
                'blocked_reason' => $reason,
            ]);
            $this->finalize($quiz, $attempt, forced: true);
        });
    }

    /* ===================== WAKTU HABIS TANPA SUBMIT =====================
       Auto-submit saat waktu habis selama ini HANYA dikerjakan timer di
       browser/app siswa (tick() di show.blade.php memanggil /submit). Kalau
       request itu tidak pernah sampai -- server mati karena listrik padam,
       jaringan sekolah putus, atau siswa menutup browser -- attempt-nya
       tertinggal "sedang mengerjakan" selamanya dan nilainya tidak pernah
       dihitung (tampil 0 / kosong di Hasil). Method di bawah ini membuat
       server sendiri yang menyelesaikan attempt yang sudah lewat waktu,
       memakai jawaban yang sempat tersimpan (jawaban disimpan per soal
       lewat saveAnswer, jadi yang sudah dijawab sebelum server mati aman). */

    /**
     * Jeda setelah waktu habis sebelum server menyelesaikan attempt sendiri,
     * supaya auto-submit dari browser/app (yang ikut mengirim ketikan isian
     * terakhir) tetap sempat sampai duluan.
     */
    public const TOLERANSI_WAKTU_HABIS_DETIK = 120;

    /** Batas waktu pengerjaan: time_start + durasi ujian (null = tidak bisa dihitung). */
    public function batasWaktu(Quiz $quiz, QuizAttempt $attempt): ?Carbon
    {
        if (! $attempt->time_start || (int) $quiz->duration <= 0) return null;

        return $attempt->time_start->copy()->addMinutes((int) $quiz->duration);
    }

    /**
     * Selesaikan attempt kalau waktunya sudah habis (lewat toleransi) tapi
     * belum pernah disubmit. time_end diisi BATAS WAKTU-nya, bukan saat ini,
     * supaya durasi pengerjaan tidak ikut terhitung sampai server menyala.
     * Return true kalau attempt barusan diselesaikan.
     */
    public function finalizeIfExpired(Quiz $quiz, QuizAttempt $attempt): bool
    {
        if (! $this->sudahKedaluwarsa($quiz, $attempt)) return false;

        $this->finalize($quiz, $attempt, forced: false, timeEnd: $this->batasWaktu($quiz, $attempt));
        return true;
    }

    /** Masih "sedang" padahal batas waktu + toleransi sudah lewat? */
    public function sudahKedaluwarsa(Quiz $quiz, QuizAttempt $attempt): bool
    {
        if ($attempt->is_done || $attempt->is_blocked) return false;

        $batas = $this->batasWaktu($quiz, $attempt);

        return $batas && now()->gte($batas->copy()->addSeconds(self::TOLERANSI_WAKTU_HABIS_DETIK));
    }

    /**
     * Sapu semua attempt yang masih "sedang" tapi waktunya sudah habis.
     * Dipanggil dari middleware SelesaikanUjianKedaluwarsa dan command
     * `ujian:selesaikan-kedaluwarsa`. Return jumlah attempt yang diselesaikan.
     */
    public function finalizeExpiredAttempts(?array $quizIds = null): int
    {
        $jumlah = 0;

        QuizAttempt::with('quiz')
            ->where('is_done', false)
            ->where('is_blocked', false)
            ->whereNotNull('time_start')
            ->when($quizIds !== null, fn ($q) => $q->whereIn('quiz_id', $quizIds))
            ->chunkById(200, function ($attempts) use (&$jumlah) {
                foreach ($attempts as $attempt) {
                    // quiz null = registrasi ujiannya sudah dihapus → biarkan
                    if ($attempt->quiz && $this->finalizeIfExpired($attempt->quiz, $attempt)) {
                        $jumlah++;
                    }
                }
            });

        return $jumlah;
    }

    public function finalize(Quiz $quiz, QuizAttempt $attempt, bool $forced = false, ?Carbon $timeEnd = null): void
    {
        if ($attempt->is_done) return;

        DB::transaction(function () use ($quiz, $attempt, $forced, $timeEnd) {
            $quiz->load('questions.question.options');
            $score = 0; $correct = 0; $wrong = 0; $empty = 0; $partial = 0;

            foreach ($quiz->questions as $qq) {
                $ans = $attempt->answers()->where('quiz_question_id', $qq->id)->first();
                $belumDijawab = ! $ans || (
                    ! $ans->question_option_id
                    && empty($ans->answer_json)
                    && ! filled($ans->answer_text)
                );
                if ($belumDijawab) {
                    $empty++; continue;
                }

                $fraction = $this->answerScoreFraction($qq->question, $ans);
                $ans->update(['is_correct' => $fraction >= 1.0, 'partial_score' => $fraction]);
                $score += $qq->marks * $fraction;
                // PGK/Penjodohan proporsional bisa jatuh di antara 0 dan 1
                // (mis. cocok 2 dari 3 pasangan) -- itu bukan "Benar" (kurang)
                // atau "Salah" (masih dapat nilai), jadi dihitung terpisah
                // sebagai "Sebagian Benar" supaya ringkasan Benar/Salah/Kosong
                // tidak menyesatkan.
                if ($fraction >= 1.0) { $correct++; }
                elseif ($fraction > 0.0) { $partial++; }
                else { $wrong++; }
            }

            // Mode "pengurangan_nilai": tiap pelanggaran (bukan cuma yang
            // melewati max_violations) langsung memotong nilai akhir. Beda
            // dari mode blokir/logout_otomatis yang menghentikan ujian saat
            // ambang batas tercapai — mode ini biarkan ujian tetap jalan,
            // potongannya baru terlihat di nilai akhir. Nilai tidak boleh minus.
            //
            // PENTING soal skala: guru mengisi `nilai_pengurangan` sebagai poin
            // di skala NILAI AKHIR 0-100 (lihat help text form: "memotong 5
            // poin dari nilai akhir"), sedangkan `$score` di sini masih di
            // skala POIN MENTAH (0..total_marks) -- baru dikonversi ke 0-100
            // belakangan oleh QuizAttempt::getNilaiAttribute(). Kalau
            // dikurangkan langsung tanpa konversi, potongannya jadi berlipat
            // ganda untuk quiz dengan sedikit soal (mis. quiz 5 soal x 1 poin,
            // potongan "5" per pelanggaran = 100% dari nilai akhir, bukan 5%)
            // sehingga satu pelanggaran ringan bisa langsung menge-nol-kan
            // nilai siswa yang sebenarnya menjawab benar semua.
            if (($quiz->proteksi_mode ?? null) === 'pengurangan_nilai' && $quiz->nilai_pengurangan > 0) {
                $totalMarks = (float) ($quiz->total_marks ?? 0);
                if ($totalMarks > 0) {
                    $potonganPoinMentah = $attempt->violation_count * (float) $quiz->nilai_pengurangan / 100 * $totalMarks;
                    $score = max(0, $score - $potonganPoinMentah);
                }
            }

            $attempt->update([
                'is_done' => true,
                'time_end' => $timeEnd ?? now(),
                'score' => $score,
                'correct_count' => $correct,
                'wrong_count' => $wrong,
                'empty_count' => $empty,
                'partial_count' => $partial,
                'is_force_submitted' => $forced,
            ]);
        });
    }
}
