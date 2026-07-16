<?php

use App\Http\Controllers\AccountStatusController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\LogLoginController;
use App\Http\Controllers\Cbt\TopikController;
use App\Http\Controllers\Cbt\BankSoalController;
use App\Http\Controllers\Cbt\TesController;
use App\Http\Controllers\Cbt\TokenSesiController;
use App\Http\Controllers\Cbt\HasilController;
use App\Http\Controllers\Cbt\MonitoringController;
use App\Http\Controllers\Cbt\UjianController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Landing page publik (portal pemilihan aplikasi) sekarang jadi project
// terpisah — lihat "landing-page". Root di sini langsung ke login/dashboard.
Route::get('/', function () {
    foreach (['admin', 'guru', 'siswa'] as $g) {
        if (Auth::guard($g)->check()) {
            return redirect()->route('dashboard');
        }
    }
    return redirect()->route('login');
});

//   Halaman status akun (selalu accessible, tanpa auth)
Route::get('/account/suspended', [AccountStatusController::class, 'suspended'])->name('account.suspended');
Route::get('/account/locked',    [AccountStatusController::class, 'locked'])->name('account.locked');
Route::get('/account/inactive',  [AccountStatusController::class, 'inactive'])->name('account.inactive');

// Guest — login umum (legacy, dipertahankan agar link/redirect lama tetap jalan)
Route::middleware('guest:admin,guru,siswa')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:30,1')   // anti brute-force
        ->name('login.post');
});

Route::get('/cbt/login', [AuthController::class, 'showLogin'])
    ->defaults('module', 'cbt')->name('cbt.login');
Route::post('/cbt/login', [AuthController::class, 'login'])
    ->defaults('module', 'cbt')->middleware('throttle:30,1')->name('cbt.login.post');

// Endpoint refresh CSRF token — dipakai login form di mobile sebelum submit
// untuk menghindari 419 saat halaman lama di-cache browser.
Route::get('/csrf-refresh', function () {
    return response()->json(['token' => csrf_token()])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
})->name('csrf.refresh');

//  Authenticated (semua proteksi diterapkan)
Route::middleware([
    'auth:admin,guru,siswa',
    'sso',             // single sign-on: kick session lama
    'accountstatus',   // cek account_status & locked_until
    'otp',             // paksa verifikasi OTP jika otp_enabled
])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // OTP — boleh diakses meski belum terverifikasi OTP (middleware otp bypass otp.*)
    Route::get('/otp', [OtpController::class, 'show'])->name('otp.show');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->name('otp.resend');
    Route::post('/otp/verify', [OtpController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('otp.verify');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profil', [ProfilController::class, 'index'])->name('profil.index');
    Route::put('/profil/password', [ProfilController::class, 'updatePassword'])->name('profil.password');

    // CBT (admin & guru)
    Route::middleware(['role:admin,guru', 'rbac'])->group(function () {
        Route::resource('topik', TopikController::class)->except('show')
            ->parameters(['topik' => 'topik']);

        // PREVIEW (HARUS sebelum resource biar tidak tertangkap {bankSoal})
        Route::get('/bank-soal/preview-mapel',    [BankSoalController::class, 'previewMapel'])->name('bank-soal.preview.mapel');
        Route::get('/bank-soal/preview-mapel/export-word', [BankSoalController::class, 'exportMapelWord'])->name('bank-soal.preview.mapel.word');

        // Import & Export Bank Soal (HARUS sebelum resource biar tidak tertangkap {bankSoal})
        Route::get('/bank-soal/import',           [BankSoalController::class, 'importForm'])->name('bank-soal.import.form');
        Route::post('/bank-soal/import',          [BankSoalController::class, 'importStore'])->name('bank-soal.import.store');
        Route::get('/bank-soal/import-template',       [BankSoalController::class, 'importTemplate'])->name('bank-soal.import.template');
        Route::get('/bank-soal/import-template-word',  [BankSoalController::class, 'importTemplateWord'])->name('bank-soal.import.template.word');
        // Export Bank Soal dipindah ke modul Kelola Soal Ujian (tes.export.word / tes.export.pdf)
        Route::post('/bank-soal/upload-image', [BankSoalController::class, 'uploadImage'])->name('bank-soal.upload-image');

        Route::resource('bank-soal', BankSoalController::class)->except('show')
            ->parameters(['bank-soal' => 'bankSoal']);
        Route::get('/bank-soal/{bankSoal}/preview', [BankSoalController::class, 'preview'])->name('bank-soal.preview');

        Route::resource('tes', TesController::class)->except('show')
            ->parameters(['tes' => 'tes']);
        // AJAX daftar siswa per rombel — untuk mode target "Per Siswa" di form registrasi
        Route::get('/tes-siswa-by-rombel', [TesController::class, 'siswaByRombel'])->name('tes.siswa-by-rombel');
        Route::get('/tes/{tes}/questions', [TesController::class, 'questions'])->name('tes.questions');
        Route::post('/tes/{tes}/questions/attach', [TesController::class, 'attachQuestion'])->name('tes.attach-question');
        Route::delete('/tes/{tes}/questions/{quizQuestion}', [TesController::class, 'detachQuestion'])->name('tes.detach-question');

        // Export soal yang sudah di-attach ke tes (pindah dari Bank Soal)
        Route::get('/tes/{tes}/export/word', [TesController::class, 'exportWord'])->name('tes.export.word');
        Route::get('/tes/{tes}/export/pdf',  [TesController::class, 'exportPdf'])->name('tes.export.pdf');

        Route::resource('token-sesi', TokenSesiController::class)->except('show')
            ->parameters(['token-sesi' => 'tokenSesi']);

        // ----- Hasil & Laporan -----
        Route::get('/hasil',                         [HasilController::class, 'index'])->name('hasil.index');
        Route::get('/hasil/export/nilai',            [HasilController::class, 'exportNilai'])->name('hasil.export.nilai');
        Route::get('/hasil/statistik',               [HasilController::class, 'statistik'])->name('hasil.statistik');
        Route::get('/hasil/statistik/export',        [HasilController::class, 'exportStatistik'])->name('hasil.statistik.export');
        Route::get('/hasil/analisis-butir',          [HasilController::class, 'analisisButir'])->name('hasil.analisis');
        Route::get('/hasil/analisis-butir/export',   [HasilController::class, 'exportAnalisisButir'])->name('hasil.analisis.export');
        Route::get('/hasil/{attempt}',               [HasilController::class, 'detail'])->name('hasil.detail');

    });

    // Modul ultra-sensitif: hanya admin (tanpa guru)
    Route::middleware('admin')->group(function () {
        // Pengaturan Aplikasi
        Route::get('/setting',  [SettingController::class, 'index'])->name('setting.index');
        Route::put('/setting',  [SettingController::class, 'update'])->name('setting.update');

        // Backup & Restore (akses lewat tab di Profil admin)
        Route::post('/backup/download', [BackupController::class, 'download'])->name('backup.download');
        Route::post('/backup/restore',  [BackupController::class, 'restore'])->name('backup.restore');

        // Log Login
        Route::get('/log-login', [LogLoginController::class, 'index'])->name('log-login.index');

        // Monitoring Ujian (realtime)
        Route::get('/monitoring',                       [MonitoringController::class, 'index'])->name('monitoring.index');
        Route::get('/monitoring/quiz/{quiz}',           [MonitoringController::class, 'detail'])->name('monitoring.detail');
        Route::post('/monitoring/{attempt}/block',      [MonitoringController::class, 'block'])->name('monitoring.block');
        Route::post('/monitoring/{attempt}/unblock',    [MonitoringController::class, 'unblock'])->name('monitoring.unblock');
        Route::delete('/monitoring/{attempt}/reset',    [MonitoringController::class, 'resetAttempt'])->name('monitoring.reset');
        Route::get('/monitoring/{attempt}/lihat',       [MonitoringController::class, 'lihat'])->name('monitoring.lihat');
    });

    // UJIAN (siswa) — dilindungi proteksi IP bila admin mengaktifkannya
    Route::middleware(['role:siswa', 'examip'])->group(function () {
        Route::get('/ujian', [UjianController::class, 'index'])->name('siswa.ujian.index');
        Route::post('/ujian/{quiz}/start', [UjianController::class, 'start'])->name('siswa.ujian.start');
        Route::get('/ujian/{quiz}/{attempt}', [UjianController::class, 'show'])->name('siswa.ujian.show');
        Route::post('/ujian/{quiz}/{attempt}/save', [UjianController::class, 'saveAnswer'])->name('siswa.ujian.save');
        Route::post('/ujian/{quiz}/{attempt}/violation', [UjianController::class, 'logViolation'])->name('siswa.ujian.violation');
        Route::post('/ujian/{quiz}/{attempt}/submit', [UjianController::class, 'submit'])->name('siswa.ujian.submit');
        Route::get('/ujian/{quiz}/{attempt}/result',  [UjianController::class, 'result'])->name('siswa.ujian.result');
        Route::get('/ujian/{quiz}/{attempt}/blocked', [UjianController::class, 'blocked'])->name('siswa.ujian.blocked');
        Route::get('/riwayat', [UjianController::class, 'riwayat'])->name('siswa.riwayat');
    });
});
