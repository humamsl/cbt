<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Middleware global yang berjalan pada setiap request
        $middleware->append(\App\Http\Middleware\UpdateUserLastSeen::class);

        // Tambahkan license check ke web group (semua halaman web)
        $middleware->web(append: [
            \App\Http\Middleware\CheckAppExpiry::class,
        ]);

        // Alias untuk dipakai di routes/web.php
        $middleware->alias([
            'role'           => \App\Http\Middleware\RoleMiddleware::class,
            'rbac'           => \App\Http\Middleware\Rbac::class,
            'otp'            => \App\Http\Middleware\OtpVerification::class,
            'accountstatus'  => \App\Http\Middleware\AccountStatus::class,
            'admin'          => \App\Http\Middleware\AdminMiddleware::class,
            'license'        => \App\Http\Middleware\CheckAppExpiry::class,
            'sso'            => \App\Http\Middleware\SingleSessionGuard::class,
            'examip'         => \App\Http\Middleware\CheckExamIp::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Tangani 419 Page Expired (TokenMismatchException) — redirect ke login
        // dengan pesan ramah, bukan halaman putih default Laravel. Banyak terjadi
        // di mobile karena browser cache halaman login & token kadaluwarsa.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi kadaluwarsa. Silakan refresh dan coba lagi.',
                ], 419);
            }
            return redirect()->route('login')
                ->with('error', 'Sesi Anda kadaluwarsa. Silakan login kembali.')
                ->withInput($request->except('password'));
        });
    })->create();
