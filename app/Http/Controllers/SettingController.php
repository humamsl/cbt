<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;

/**
 * Identitas sekolah, logo, halaman login, dan identitas aplikasi sekarang
 * dikelola terpusat di aplikasi Data Center (menu Pengaturan Aplikasi) supaya
 * seragam di semua aplikasi — lihat Data Center\PengaturanController &
 * Api\PublicStatsController::branding(). CBT hanya menyisakan Proteksi IP
 * (khusus ujian siswa) dan Backup/Restore Bank Soal (data milik CBT sendiri).
 */
class SettingController extends Controller
{
    public function index(Request $request)
    {
        return view('setting.index', [
            'app' => [
                'ip_protection_enabled' => (bool) AppSetting::get('ip_protection_enabled', false),
                'allowed_ips'           => (string) AppSetting::get('allowed_ips', ''),
            ],
            'currentIp' => $this->detectClientIp($request),
            'ipHeaders' => $this->ipHeaders($request),
        ]);
    }

    protected function detectClientIp(\Illuminate\Http\Request $r): string
    {
        if ($cf = $r->header('CF-Connecting-IP')) {
            return $cf;
        }
        if ($xff = $r->header('X-Forwarded-For')) {
            return trim(explode(',', $xff)[0]);
        }
        return $r->ip() ?: '0.0.0.0';
    }

    protected function ipHeaders(\Illuminate\Http\Request $r): array
    {
        return [
            'REMOTE_ADDR'      => $r->server('REMOTE_ADDR'),
            'X-Forwarded-For'  => $r->header('X-Forwarded-For'),
            'CF-Connecting-IP' => $r->header('CF-Connecting-IP'),
        ];
    }

    /** Validasi format IP atau CIDR (IPv4/IPv6). */
    protected function isValidCidrOrIp(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP)) return true;
        if (! str_contains($value, '/')) return false;
        [$ip, $mask] = explode('/', $value, 2);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) return false;
        $mask = (int) $mask;
        $max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;
        return $mask >= 0 && $mask <= $max;
    }

    public function update(Request $request)
    {
        // ---- Proteksi IP (CIDR) untuk ujian siswa ----
        $enabled = $request->boolean('ip_protection_enabled');
        $raw = (string) $request->input('allowed_ips', '');

        // Validasi tiap baris CIDR (best-effort, biar admin tahu kalau salah ketik)
        $invalid = [];
        foreach (\App\Http\Middleware\CheckExamIp::parseList($raw) as $line) {
            if (! $this->isValidCidrOrIp($line)) $invalid[] = $line;
        }
        if ($invalid) {
            return back()->with('error', 'Format IP/CIDR tidak valid: '.implode(', ', $invalid));
        }

        AppSetting::set('ip_protection_enabled', $enabled ? 1 : 0, 'bool', 'proteksi');
        AppSetting::set('allowed_ips', $raw, 'text', 'proteksi');
        AppSetting::flush();

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
