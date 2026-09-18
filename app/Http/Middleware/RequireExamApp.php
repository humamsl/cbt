<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;

/**
 * Menutup akses siswa ke halaman ujian versi WEB kalau admin sekolah sudah
 * mewajibkan pengerjaan ujian lewat Aplikasi CBT Siswa (Android/iOS) --
 * lihat AppSetting `app_only_exam_enabled` di menu Pengaturan Aplikasi.
 *
 * Default OFF supaya sekolah yang belum siap pakai aplikasi (belum
 * dibagikan/di-install ke perangkat siswa) tidak tiba-tiba kehilangan akses
 * web mereka -- ini SATU-satunya jalur ujian sebelum aplikasi mobile ada.
 *
 * Endpoint API mobile (routes/api.php, auth:sanctum) TIDAK lewat middleware
 * ini sama sekali -- jadi mengaktifkan toggle ini tidak pernah memblokir
 * aplikasi itu sendiri, hanya jalur web based-session siswa.
 */
class RequireExamApp
{
    public function handle(Request $request, Closure $next)
    {
        if (! (bool) AppSetting::get('app_only_exam_enabled', false)) {
            return $next($request);
        }

        return response()->view('errors.gunakan-aplikasi', [], 403);
    }
}
