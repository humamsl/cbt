<?php

namespace App\Http\Middleware;

use App\Services\Ujian\ExamScoringService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Menyelesaikan otomatis attempt ujian yang waktunya sudah habis tapi tidak
 * pernah disubmit (server mati karena listrik padam, siswa menutup browser,
 * dsb.) -- lihat ExamScoringService::finalizeExpiredAttempts().
 *
 * Sengaja TIDAK bergantung pada cron/scheduler (server sekolah umumnya
 * tidak memasangnya):
 *  - Dipasang global → setiap request memicu "sapuan" di belakang layar,
 *    maksimal 1x per menit, SETELAH respons terkirim ke browser (terminate)
 *    sehingga tidak memperlambat siswa yang sedang ujian.
 *  - Mode `:langsung` (halaman Monitoring & Hasil) → sapuan dijalankan
 *    SEBELUM halaman dibuat, supaya guru langsung melihat status "Selesai"
 *    dan nilai yang benar, bukan "Sedang" yang basi.
 */
class SelesaikanUjianKedaluwarsa
{
    public function __construct(protected ExamScoringService $scoring) {}

    public function handle(Request $request, Closure $next, ?string $mode = null)
    {
        if ($mode === 'langsung') {
            $this->sapu();
        }

        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (! Cache::add('ujian:sapu-kedaluwarsa', true, 60)) return;

        $this->sapu();
    }

    protected function sapu(): void
    {
        try {
            $jumlah = $this->scoring->finalizeExpiredAttempts();
            if ($jumlah > 0) {
                Log::info("Ujian kedaluwarsa diselesaikan otomatis: {$jumlah} attempt.");
            }
        } catch (\Throwable $e) {
            // Jangan sampai halaman ikut error karena sapuan ini gagal --
            // attempt yang tertinggal akan dicoba lagi di sapuan berikutnya.
            report($e);
        }
    }
}
