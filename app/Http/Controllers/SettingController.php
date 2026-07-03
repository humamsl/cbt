<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Sekolah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $sekolah = Sekolah::first() ?? new Sekolah();
        return view('setting.index', [
            'sekolah' => $sekolah,
            'app' => [
                'app_name'              => AppSetting::get('app_name', config('app.name')),
                'app_tagline'           => AppSetting::get('app_tagline', 'Sistem Informasi Sekolah Terintegrasi'),
                'theme_color'           => AppSetting::get('theme_color', '#0d9488'),
                'logo'                  => AppSetting::get('logo'),
                'favicon'               => AppSetting::get('favicon'),
                'login_bg'              => AppSetting::get('login_bg'),
                'login_title'           => AppSetting::get('login_title', 'Selamat datang di platform CBT Modern sekolah Anda.'),
                'login_subtitle'        => AppSetting::get('login_subtitle', 'Kelola data guru, siswa, kelas, dan ujian online dalam satu dashboard yang cepat, aman, dan mudah digunakan.'),
                'footer_text'           => AppSetting::get('footer_text'),
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
        // Identitas sekolah (NPSN, alamat, kepala sekolah, dst) dikelola di
        // aplikasi Data Center sekarang — CBT hanya menampilkan salinannya
        // (lihat SettingController::index & `datacenter:sync`), tidak lagi
        // mengeditnya di sini.

        // ---- File upload (logo, favicon, login_bg) ----
        $request->validate([
            'logo'     => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'favicon'  => 'nullable|image|mimes:png,ico,svg|max:512',
            'login_bg' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:5120',
        ]);

        foreach (['logo', 'favicon', 'login_bg'] as $field) {
            if ($request->hasFile($field)) {
                // hapus file lama
                $oldPath = AppSetting::get($field);
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
                $path = $request->file($field)->store('settings', 'public');
                AppSetting::set($field, $path, 'file', 'tampilan', ucfirst($field));
            }
        }

        // ---- Setting teks & warna ----
        $textKeys = [
            'app_name'       => 'string',
            'app_tagline'    => 'string',
            'login_title'    => 'string',
            'login_subtitle' => 'string',
            'footer_text'    => 'string',
            'theme_color'    => 'color',
        ];
        foreach ($textKeys as $key => $type) {
            $val = $request->input($key);
            if ($val !== null) {
                AppSetting::set($key, $val, $type === 'color' ? 'color' : 'text', 'tampilan');
            }
        }

        // ---- Hapus file (jika user klik tombol hapus) ----
        foreach ((array) $request->input('remove_file', []) as $field) {
            $oldPath = AppSetting::get($field);
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            AppSetting::set($field, null, 'file', 'tampilan');
        }

        // ---- Proteksi IP (CIDR) untuk ujian siswa ----
        if ($request->has('ip_protection_enabled') || $request->has('allowed_ips')) {
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
        }

        AppSetting::flush();

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
