<?php

namespace App\Console\Commands;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hitung ulang `score` attempt yang selesai di quiz bermode proteksi
 * "pengurangan_nilai" (Kurangi Nilai).
 *
 * KENAPA INI PERLU: sebelum diperbaiki, UjianController::finalize() memotong
 * `score` (skala POIN MENTAH 0..total_marks) langsung pakai nilai_pengurangan
 * yang diisi guru di skala NILAI AKHIR 0-100 (lihat help text di form Registrasi
 * Ujian). Akibatnya potongannya berlipat ganda pada quiz dengan sedikit soal —
 * 1 pelanggaran bisa langsung menge-nol-kan nilai siswa yang sebenarnya
 * menjawab benar. Jawaban siswa TIDAK hilang (kolom is_correct per jawaban dan
 * correct_count/wrong_count/empty_count semuanya tetap benar) — yang salah
 * cuma `score` akhirnya. Command ini menghitung ulang `score` dari jawaban yang
 * tersimpan (bukan dari ulang assessment), lalu menerapkan rumus potongan yang
 * sudah dibetulkan.
 *
 * Default: hanya MELAPORKAN attempt yang bakal berubah. Tambahkan --terapkan
 * untuk benar-benar menyimpan.
 */
class PerbaikiNilaiPengurangan extends Command
{
    protected $signature = 'ujian:perbaiki-nilai-pengurangan
        {--quiz= : Batasi ke satu quiz_id saja (default: semua quiz mode Kurangi Nilai)}
        {--terapkan : Simpan perubahan. Tanpa opsi ini hanya melaporkan}';

    protected $description = 'Hitung ulang nilai attempt yang salah dipotong akibat bug skala pengurangan_nilai';

    public function handle(): int
    {
        $terapkan = (bool) $this->option('terapkan');

        $quizzes = Quiz::where('proteksi_mode', 'pengurangan_nilai')
            ->where('nilai_pengurangan', '>', 0)
            ->when($this->option('quiz'), fn ($q) => $q->where('id', (int) $this->option('quiz')))
            ->get();

        if ($quizzes->isEmpty()) {
            $this->info('Tidak ada quiz bermode "Kurangi Nilai" yang cocok.');

            return self::SUCCESS;
        }

        $totalDiperbaiki = 0;
        $rows = [];

        foreach ($quizzes as $quiz) {
            $totalMarks = (float) ($quiz->total_marks ?? 0);
            if ($totalMarks <= 0) continue;

            $attempts = QuizAttempt::with('answers.quizQuestion')
                ->where('quiz_id', $quiz->id)
                ->where('is_done', true)
                ->where('violation_count', '>', 0)
                ->get();

            foreach ($attempts as $a) {
                $skorMentahBenar = $a->answers->filter(fn ($ans) => $ans->is_correct)
                    ->sum(fn ($ans) => (float) ($ans->quizQuestion->marks ?? 0));

                $potongan = $a->violation_count * (float) $quiz->nilai_pengurangan / 100 * $totalMarks;
                $skorBaru = max(0, round($skorMentahBenar - $potongan, 4));
                $skorLama = (float) ($a->score ?? 0);

                if (abs($skorBaru - $skorLama) < 0.0001) continue;

                $totalDiperbaiki++;
                $rows[] = [
                    $quiz->id, $quiz->name, $a->siswa_id,
                    $skorLama, round($skorLama / $totalMarks * 100, 1),
                    $skorBaru, round($skorBaru / $totalMarks * 100, 1),
                ];

                if ($terapkan) {
                    $a->update(['score' => $skorBaru]);
                }
            }
        }

        if ($totalDiperbaiki === 0) {
            $this->info('Tidak ada attempt yang perlu diperbaiki — nilainya sudah benar.');

            return self::SUCCESS;
        }

        $this->table(
            ['quiz_id', 'quiz', 'siswa_id', 'score lama', 'nilai lama', 'score baru', 'nilai baru'],
            array_slice($rows, 0, 50)
        );
        if (count($rows) > 50) {
            $this->line('... dan '.(count($rows) - 50).' baris lainnya (tidak semua ditampilkan).');
        }

        $awalan = $terapkan ? '' : '[dry-run] ';
        $this->newLine();
        $this->info("{$awalan}{$totalDiperbaiki} attempt akan diperbaiki nilainya.");

        if (! $terapkan) {
            $this->line('Tidak ada yang disimpan. Tambahkan --terapkan untuk menjalankan sungguhan.');
        }

        return self::SUCCESS;
    }
}
