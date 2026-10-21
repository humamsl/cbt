<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Batas login GAGAL per akun admin dalam 24 jam, dari IP mana pun.
 *
 * Lockout 5x gagal / 15 menit saja masih membolehkan ±480 tebakan per hari
 * per akun, dan batas per-IP tidak berlaku kalau penyerang memakai banyak IP.
 * Batas ini menutup celah itu. Konsekuensinya penyerang bisa mengunci akun
 * admin asli sampai 24 jam — buka lagi dengan `php artisan admin:reset-credentials`
 * (yang memanggil clear()) atau tunggu jendelanya habis.
 */
class LoginFailureCap
{
    public const MAX_FAILURES = 20;

    public const WINDOW_SECONDS = 86400;

    public static function key(string $username): string
    {
        return 'login-fail-24h:'.sha1(Str::lower(trim($username)));
    }

    /** Sisa detik penguncian, atau null kalau akun masih boleh mencoba. */
    public static function blockedFor(string $username): ?int
    {
        $key = self::key($username);

        return RateLimiter::tooManyAttempts($key, self::MAX_FAILURES) ? RateLimiter::availableIn($key) : null;
    }

    public static function message(int $seconds): string
    {
        return 'Terlalu banyak percobaan login gagal untuk akun ini. Akun dikunci ±'
            .max(1, (int) ceil($seconds / 3600)).' jam. Hubungi pengelola server untuk membukanya.';
    }

    public static function hit(string $username): void
    {
        RateLimiter::hit(self::key($username), self::WINDOW_SECONDS);
    }

    public static function clear(string $username): void
    {
        RateLimiter::clear(self::key($username));
    }
}
