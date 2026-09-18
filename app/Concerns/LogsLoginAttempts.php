<?php

namespace App\Concerns;

use App\Models\LoginAttempt;
use Illuminate\Http\Request;

/**
 * Catat percobaan login (berhasil/gagal) ke tabel `login_attempts` untuk
 * halaman admin "Log Login" — dipakai bersama oleh AuthController (web) dan
 * Api\AuthController (aplikasi mobile) supaya kedua jalur login tercatat
 * dengan format yang sama persis, bukan diduplikasi/berisiko menyimpang.
 */
trait LogsLoginAttempts
{
    protected function logAttempt(Request $r, string $username, string $role, bool $success): void
    {
        $ua = (string) $r->userAgent();
        $parsed = $this->parseUserAgent($ua);

        $attemptNo = LoginAttempt::where('username', $username)
            ->whereDate('created_at', today())->count() + 1;

        LoginAttempt::create([
            'username'   => $username,
            'guard'      => $role,
            'success'    => $success,
            'ip_address' => $r->ip(),
            'user_agent' => substr($ua, 0, 500),
            'device_type'=> $parsed['device'],
            'browser'    => $parsed['browser'],
            'os'         => $parsed['os'],
            'attempt_no' => $attemptNo,
        ]);
    }

    /** Parser sederhana user agent → device/browser/OS */
    protected function parseUserAgent(string $ua): array
    {
        $device = 'desktop';
        if (preg_match('/iPad|Tablet/i', $ua)) $device = 'tablet';
        elseif (preg_match('/Mobi|Android|iPhone|iPod|BlackBerry|Opera Mini/i', $ua)) $device = 'mobile';

        $browser = 'Other';
        if (preg_match('/Edg\//i', $ua)) $browser = 'Edge';
        elseif (preg_match('/Chrome\//i', $ua)) $browser = 'Chrome';
        elseif (preg_match('/Firefox\//i', $ua)) $browser = 'Firefox';
        elseif (preg_match('/Safari\//i', $ua) && ! preg_match('/Chrome|Edg/i', $ua)) $browser = 'Safari';
        elseif (preg_match('/OPR\/|Opera/i', $ua)) $browser = 'Opera';

        $os = 'Other';
        if (preg_match('/Windows NT 10/i', $ua)) $os = 'Windows 10/11';
        elseif (preg_match('/Windows NT/i', $ua)) $os = 'Windows';
        elseif (preg_match('/Mac OS X/i', $ua)) $os = 'macOS';
        elseif (preg_match('/Android/i', $ua)) $os = 'Android';
        elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $os = 'iOS';
        elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';

        return compact('device', 'browser', 'os');
    }
}
