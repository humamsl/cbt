<?php

namespace App\Console\Commands;

use App\Models\QuizAttempt;
use App\Services\Ujian\ExamScoringService;
use Illuminate\Console\Command;

/**
 * Selesaikan attempt ujian yang masih berstatus "Sedang" padahal waktu
 * pengerjaannya (mulai + durasi) sudah habis -- kasus khas: server mati
 * karena listrik padam saat ujian berlangsung, sehingga auto-submit dari
 * browser siswa tidak pernah sampai dan nilainya tercatat 0/kosong.
 *
 * Nilai dihitung dari jawaban yang SUDAH tersimpan sebelum server mati
 * (jawaban disimpan per soal setiap kali siswa memilih/mengetik), persis
 * seperti kalau siswa menekan tombol kirim saat waktu habis. time_end diisi
 * batas waktunya, bukan saat command dijalankan.
 *
 * Aplikasi juga melakukan ini otomatis (middleware SelesaikanUjianKedaluwarsa
 * + halaman Monitoring/Hasil); command ini untuk membereskan sekaligus &
 * melihat daftarnya, mis. tepat setelah server menyala kembali.
 *
 * Default: hanya MELAPORKAN. Tambahkan --terapkan untuk benar-benar menyimpan.
 */
class SelesaikanUjianKedaluwarsa extends Command
{
    protected $signature = 'ujian:selesaikan-kedaluwarsa
        {--quiz= : Batasi ke satu quiz_id saja (default: semua ujian)}
        {--terapkan : Simpan perubahan. Tanpa opsi ini hanya melaporkan}';

    protected $description = 'Selesaikan & hitung nilai attempt ujian yang waktunya sudah habis tapi tidak pernah tersubmit (mis. server mati saat ujian)';

    public function handle(ExamScoringService $scoring): int
    {
        $terapkan = (bool) $this->option('terapkan');

        $sedang = QuizAttempt::with('quiz', 'siswa')
            ->withCount('answers')
            ->where('is_done', false)
            ->where('is_blocked', false)
            ->whereNotNull('time_start')
            ->when($this->option('quiz'), fn ($q) => $q->where('quiz_id', (int) $this->option('quiz')))
            ->orderBy('quiz_id')->orderBy('id')
            ->get();

        // quiz null = registrasi ujiannya sudah dihapus → tidak disentuh
        $adaUjian = $sedang->filter(fn ($a) => $a->quiz);
        $kedaluwarsa = $adaUjian->filter(fn ($a) => $scoring->sudahKedaluwarsa($a->quiz, $a));
        $masihBerjalan = $adaUjian->count() - $kedaluwarsa->count();

        if ($kedaluwarsa->isEmpty()) {
            $this->info('Tidak ada attempt yang waktunya habis tapi belum tersubmit.');
            if ($masihBerjalan > 0) {
                $this->line("{$masihBerjalan} attempt masih dalam waktu pengerjaan (tidak diubah).");
            }

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($kedaluwarsa as $a) {
            $batas = $scoring->batasWaktu($a->quiz, $a);
            if ($terapkan) {
                $scoring->finalize($a->quiz, $a, forced: false, timeEnd: $batas);
            }
            $rows[] = [
                $a->id,
                $a->siswa->nama_siswa ?? '#'.$a->siswa_id,
                '#'.$a->quiz_id.' '.$a->quiz->name,
                $a->time_start->format('d-m-Y H:i'),
                $batas->format('d-m-Y H:i'),
                $a->answers_count,
                $terapkan ? $a->nilai : '-',
            ];
        }

        $this->table(['Attempt', 'Siswa', 'Ujian', 'Mulai', 'Batas Waktu', 'Jawaban Tersimpan', 'Nilai'], $rows);

        if ($masihBerjalan > 0) {
            $this->line("{$masihBerjalan} attempt lain masih dalam waktu pengerjaan (tidak diubah).");
        }

        if ($terapkan) {
            $this->info(count($rows).' attempt diselesaikan & nilainya dihitung.');
        } else {
            $this->warn(count($rows).' attempt akan diselesaikan. Jalankan ulang dengan --terapkan untuk menyimpan.');
        }

        return self::SUCCESS;
    }
}
