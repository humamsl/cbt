<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use App\Models\Siswa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Membatasi akses ujian SISWA berdasarkan IP / CIDR yang sudah di-set admin.
 * - Hanya berlaku jika setting `ip_protection_enabled = 1` dan ada minimal 1 entry di `allowed_ips`.
 * - Admin & guru bypass.
 * - Mendukung CIDR (mis. 192.168.10.0/24, 10.10.0.0/16) dan IP tunggal (157.10.66.10).
 */
class CheckExamIp
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user instanceof Siswa) return $next($request);

        $enabled = (bool) AppSetting::get('ip_protection_enabled', false);
        if (! $enabled) return $next($request);

        $raw = (string) AppSetting::get('allowed_ips', '');
        $rules = $this->parseList($raw);
        if (empty($rules)) return $next($request);

        $clientIp = $this->resolveClientIp($request);

        if (! IpUtils::checkIp($clientIp, $rules)) {
            return response()->view('errors.ip-blocked', [
                'clientIp' => $clientIp,
                'rules'    => $rules,
            ], 403);
        }

        return $next($request);
    }

    /** Ambil IP yang paling representatif (cek proxy header). */
    protected function resolveClientIp(Request $request): string
    {
        if ($cf = $request->header('CF-Connecting-IP')) {
            return $cf;
        }
        if ($xff = $request->header('X-Forwarded-For')) {
            return trim(explode(',', $xff)[0]);
        }
        return $request->ip() ?: '0.0.0.0';
    }

    /** Parse textarea (multi-line) jadi array CIDR/IP yang valid. */
    public static function parseList(?string $raw): array
    {
        if (! $raw) return [];
        $items = preg_split('/[\r\n,]+/', $raw);
        $clean = [];
        foreach ($items as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            $clean[] = $line;
        }
        return $clean;
    }
}
