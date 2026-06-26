<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Lisensi aplikasi: kunci aktivasi `APP_EXPIRE_DATE` di .env berisi
 * Crypt::encryptString('YYYY-MM-DD') atau Crypt::encryptString('unlimited').
 *
 * Cara generate: `php artisan app:license 2026-12-31` atau `app:license unlimited`
 */
class CheckAppExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $encrypted = env('APP_EXPIRE_DATE');

        if (! $encrypted) {
            return response()->view('errors.expired', [
                'judul' => 'Lisensi Tidak Ditemukan',
                'pesan' => 'Variabel APP_EXPIRE_DATE belum di-set di file .env. Hubungi pengembang.',
            ], 403);
        }

        try {
            $value = Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            return response()->view('errors.expired', [
                'judul' => 'Lisensi Tidak Valid',
                'pesan' => 'Kode lisensi tidak sesuai dengan APP_KEY aplikasi ini.',
            ], 403);
        }

        if ($value === 'unlimited') {
            return $next($request);
        }

        try {
            $expiry = Carbon::parse($value)->endOfDay();
        } catch (\Throwable $e) {
            return response()->view('errors.expired', [
                'judul' => 'Format Lisensi Salah',
                'pesan' => 'Nilai lisensi tidak dapat dibaca sebagai tanggal.',
            ], 403);
        }

        if (Carbon::now()->gt($expiry)) {
            return response()->view('errors.expired', [
                'judul' => 'Masa Berlaku Aplikasi Habis',
                'pesan' => 'Lisensi aplikasi berakhir pada '.$expiry->translatedFormat('d F Y').'. Silakan hubungi pengembang untuk perpanjangan.',
            ], 403);
        }

        // Sisipkan info masa berlaku ke view (header bisa pakai)
        view()->share('appExpiryDate', $expiry);

        return $next($request);
    }
}
