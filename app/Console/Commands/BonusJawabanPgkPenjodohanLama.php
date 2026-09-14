<?php

namespace App\Console\Commands;

use App\Models\QuizAttemptAnswer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Beri nilai PENUH (bonus) untuk jawaban PGK & Penjodohan yang dikerjakan
 * SEBELUM bug jenis soal itu diperbaiki.
 *
 * LATAR: sebelum diperbaiki, soal PGK & Penjodohan tampil sebagai radio
 * pilih-SATU di halaman ujian (bukan checkbox utk PGK / dropdown pasangan
 * utk Penjodohan), dan dinilai cuma mencocokkan SATU opsi terpilih ke opsi
 * is_correct PERTAMA di database. Siswa yang mengerjakan kedua jenis soal
 * ini saat bug masih ada TIDAK PERNAH punya kesempatan menjawab sesuai
 * maksud soalnya -- UI-nya sendiri tidak mengizinkan multi-pilih/memasangkan.
 * Sebagai kompensasi, jawaban yang dikerjakan di bawah bug ini diberi NILAI
 * PENUH, bukan dihitung ulang dari pilihan lama mereka yang memang tidak
 * representatif (mustahil benar sesuai definisi soalnya).
 *
 * IDENTIFIKASI OTOMATIS jawaban "lama"/kena bug (TANPA perlu tanggal cutoff):
 * kode BARU (setelah fix) selalu menyimpan pilihan PGK/Penjodohan di kolom
 * `answer_json` dan MENGOSONGKAN `question_option_id` untuk kedua jenis itu.
 * Kode LAMA (sebelum fix) sebaliknya -- HANYA mengisi `question_option_id`
 * (satu nilai, dari radio) dan TIDAK PERNAH mengisi `answer_json` sama
 * sekali (kolom itu belum ada). Jadi baris PGK/Penjodohan dengan
 * `question_option_id` terisi DAN `answer_json` kosong = pasti dijawab di
 * bawah kode lama yang bermasalah -- sinyal ini 100% akurat, tidak perlu
 * menebak dari tanggal deploy.
 *
 * Attempt yang punya >1 soal PGK/Penjodohan lama dihitung ulang totalnya
 * SEKALI saja (score, correct_count, wrong_count, partial_count,
 * empty_count) berdasarkan SEMUA jawabannya -- soal lain (PG/Benar-Salah/
 * Fill-blank) di attempt yang sama TIDAK disentuh, tetap pakai is_correct/
 * partial_score yang sudah ada.
 *
 * Default: hanya MELAPORKAN yang akan berubah. Tambahkan --terapkan untuk
 * benar-benar menyimpan (dibungkus satu transaksi -- kalau ada error di
 * tengah, semuanya batal, bukan setengah tersimpan).
 */
class BonusJawabanPgkPenjodohanLama extends Command
{
    protected $signature = 'ujian:bonus-pgk-penjodohan-lama
        {--quiz= : Batasi ke satu quiz_id saja (default: semua quiz)}
        {--terapkan : Simpan perubahan. Tanpa opsi ini hanya melaporkan (dry-run)}';

    protected $description = 'Beri nilai penuh (bonus) utk jawaban PGK/Penjodohan yg dikerjakan sebelum bug jenis soal itu diperbaiki';

    public function handle(): int
    {
        $terapkan = (bool) $this->option('terapkan');

        $jawabanLama = QuizAttemptAnswer::query()
            ->whereNotNull('question_option_id')
            ->whereNull('answer_json')
            ->whereHas('quizQuestion.question.type', fn ($q) => $q->whereIn('slug', ['pgk', 'penjodohan']))
            ->whereHas('attempt', function ($q) {
                $q->where('is_done', true);
                if ($this->option('quiz')) {
                    $q->where('quiz_id', (int) $this->option('quiz'));
                }
            })
            ->with(['attempt.quiz.questions', 'quizQuestion.question.type'])
            ->get();

        if ($jawabanLama->isEmpty()) {
            $this->info('Tidak ada jawaban PGK/Penjodohan dari kode lama yang perlu diberi bonus.');

            return self::SUCCESS;
        }

        // Kelompokkan per attempt -- satu attempt bisa punya beberapa soal
        // PGK/Penjodohan lama, tapi total nilainya cuma dihitung ulang SEKALI.
        $perAttempt = $jawabanLama->groupBy('quiz_attempt_id');

        $rows = [];
        $totalJawabanDiperbaiki = 0;

        DB::transaction(function () use ($perAttempt, $terapkan, &$rows, &$totalJawabanDiperbaiki) {
            foreach ($perAttempt as $attemptId => $jawabanPgkPenjodohan) {
                $attempt = $jawabanPgkPenjodohan->first()->attempt;
                $quiz = $attempt?->quiz;
                if (! $attempt || ! $quiz) continue;

                $skorLama = (float) ($attempt->score ?? 0);
                $idsLama = $jawabanPgkPenjodohan->pluck('id')->all();

                // PENTING: iterasi dari SEMUA SOAL quiz ini ($quiz->questions),
                // BUKAN cuma baris quiz_attempt_answers yang ada -- soal yang
                // sama sekali tidak dijawab (tidak ada baris jawaban sama
                // sekali, bukan cuma kosong) tidak boleh hilang dari
                // perhitungan "Kosong". Pola ini sengaja disamakan persis
                // dengan UjianController::finalize() supaya hasilnya konsisten
                // dgn perhitungan asli, cuma soal PGK/Penjodohan lama yang
                // di-override jadi nilai penuh.
                $jawabanByQuestionId = QuizAttemptAnswer::where('quiz_attempt_id', $attempt->id)
                    ->get()->keyBy('quiz_question_id');

                $skorBaru = 0.0; $correct = 0; $wrong = 0; $partial = 0; $empty = 0;
                foreach ($quiz->questions as $qq) {
                    $a = $jawabanByQuestionId->get($qq->id);
                    $marks = (float) $qq->marks;
                    $kenaBug = $a && in_array($a->id, $idsLama, true);

                    if ($kenaBug) {
                        $fraction = 1.0; // bonus: nilai penuh, tidak dihitung dari pilihan lama
                    } else {
                        $belumDijawab = ! $a || (! $a->question_option_id && empty($a->answer_json) && ! filled($a->answer_text));
                        if ($belumDijawab) { $empty++; continue; }
                        $fraction = $a->partial_score !== null
                            ? (float) $a->partial_score
                            : ($a->is_correct ? 1.0 : 0.0);
                    }

                    $skorBaru += $marks * $fraction;
                    if ($fraction >= 1.0) $correct++;
                    elseif ($fraction > 0.0) $partial++;
                    else $wrong++;
                }
                $skorBaru = round($skorBaru, 4);

                // Idempoten SECARA PELAPORAN juga (bukan cuma "aman kalau
                // dijalankan ulang"): kolom identifikasi (question_option_id
                // terisi + answer_json kosong) TETAP begitu selamanya --
                // menjalankan ulang command ini akan terus menemukan baris
                // yang SAMA lagi walau sudah pernah dibonus. Tanpa cek ini,
                // command akan terus melaporkan "2 attempt diperbaiki" tiap
                // dijalankan ulang walau sebenarnya tidak ada apa-apa lagi
                // yang berubah -- membingungkan, seolah ada yang salah.
                $sudahDibonus = abs($skorBaru - $skorLama) < 0.0001
                    && $jawabanPgkPenjodohan->every(fn ($a) => $a->is_correct && abs((float) $a->partial_score - 1.0) < 0.0001);
                if ($sudahDibonus) continue;

                if ($terapkan) {
                    foreach ($jawabanPgkPenjodohan as $ans) {
                        $ans->update(['is_correct' => true, 'partial_score' => 1.0]);
                    }
                    $attempt->update([
                        'score' => $skorBaru,
                        'correct_count' => $correct,
                        'wrong_count' => $wrong,
                        'partial_count' => $partial,
                        'empty_count' => $empty,
                    ]);
                }

                $totalMarks = (float) ($quiz->total_marks ?: 0);
                $totalJawabanDiperbaiki += count($idsLama);
                $rows[] = [
                    $quiz->id,
                    Str::limit($quiz->name, 22),
                    $attempt->siswa_id,
                    count($idsLama),
                    round($skorLama, 2),
                    $totalMarks > 0 ? round($skorLama / $totalMarks * 100, 1) : '-',
                    $skorBaru,
                    $totalMarks > 0 ? round($skorBaru / $totalMarks * 100, 1) : '-',
                ];
            }
        }, 3);

        if (empty($rows)) {
            $this->info('Semua jawaban PGK/Penjodohan yang kena bug sudah pernah diberi bonus sebelumnya -- tidak ada yang perlu diubah lagi.');

            return self::SUCCESS;
        }

        $this->table(
            ['quiz_id', 'quiz', 'siswa_id', 'soal kena bug', 'score lama', 'nilai lama', 'score baru', 'nilai baru'],
            array_slice($rows, 0, 60)
        );
        if (count($rows) > 60) {
            $this->line('... dan '.(count($rows) - 60).' attempt lainnya (tidak semua ditampilkan).');
        }

        $awalan = $terapkan ? '' : '[dry-run] ';
        $this->newLine();
        $this->info("{$awalan}".count($rows)." attempt (total {$totalJawabanDiperbaiki} jawaban PGK/Penjodohan) diberi nilai penuh.");

        if (! $terapkan) {
            $this->line('Tidak ada yang disimpan. Tambahkan --terapkan untuk menjalankan sungguhan.');
        }

        return self::SUCCESS;
    }
}
