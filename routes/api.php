<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UjianController;
use App\Http\Controllers\Cbt\UjianController as WebUjianController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — APLIKASI CBT (siswa, Flutter Android & iOS)
|--------------------------------------------------------------------------
| Hanya untuk siswa. Guard-nya Sanctum token (bukan session), jadi TIDAK
| perlu CSRF sama sekali (grup middleware "api" bawaan Laravel tidak
| menyertakan ValidateCsrfToken). "Satu akun satu perangkat" ditegakkan
| dengan mencabut token lama saat login baru (lihat Api\AuthController),
| BUKAN lewat middleware 'sso' (itu 100% berbasis session ID cookie web,
| tidak cocok untuk klien token).
|
| ping / saveAnswer / logViolation sengaja TIDAK diduplikasi jadi endpoint
| baru -- ketiganya didaftarkan ulang langsung ke Cbt\UjianController yang
| sudah ada (sudah JSON sejak awal) supaya simpan-jawaban & lapor-
| pelanggaran satu-satunya sumber kebenaran untuk web maupun aplikasi.
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);

Route::middleware(['auth:sanctum', 'examip'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/ujian', [UjianController::class, 'index']);
    Route::post('/ujian/{quiz}/start', [UjianController::class, 'start']);
    Route::get('/ujian/{quiz}/{attempt}', [UjianController::class, 'show']);
    Route::post('/ujian/{quiz}/{attempt}/submit', [UjianController::class, 'submit']);
    Route::get('/ujian/{quiz}/{attempt}/result', [UjianController::class, 'result']);
    Route::get('/riwayat', [UjianController::class, 'riwayat']);

    // Reuse langsung -- lihat catatan di atas.
    Route::get('/ujian/{quiz}/{attempt}/ping', [WebUjianController::class, 'ping']);
    Route::post('/ujian/{quiz}/{attempt}/save', [WebUjianController::class, 'saveAnswer']);
    Route::post('/ujian/{quiz}/{attempt}/violation', [WebUjianController::class, 'logViolation']);
});
