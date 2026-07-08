<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Models\DatacenterAppSetting;
use App\Models\Sekolah;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Bagikan pengaturan aplikasi ke semua view
        View::composer('*', function ($view) {
            // Skip jika tabel belum migrate (mis. saat install)
            if (! Schema::hasTable('app_settings')) {
                $view->with('AppCfg', $this->defaults());
                return;
            }

            // Branding (nama + logo + favicon + background login) bersumber dari
            // Data Center supaya seragam dengan landing-page & Data Center itu
            // sendiri. TIDAK di-cache (sengaja) — perubahan di Pengaturan
            // Aplikasi Data Center harus langsung kelihatan tanpa delay.
            // Fallback ke pengaturan lokal kalau Data Center tak terjangkau.
            // Background halaman login TIDAK lagi bisa di-upload lokal di CBT
            // — diatur terpusat di Data Center (menu Pengaturan Aplikasi),
            // lihat Setting/index.blade.php.
            $branding  = $this->datacenterBranding();
            $localLogo = AppSetting::get('logo');
            $localFav  = AppSetting::get('favicon');
            $localLoginBg = AppSetting::get('login_bg');

            $view->with('AppCfg', [
                'app_name'       => $branding['school_name'] ?? AppSetting::get('app_name', config('app.name')),
                'app_tagline'    => AppSetting::get('app_tagline', 'Sistem Informasi Sekolah Terintegrasi'),
                'theme_color'    => AppSetting::get('theme_color', '#0d9488'),
                'logo'           => $localLogo,
                'favicon'        => $localFav,
                // URL siap-pakai: pakai logo Data Center bila ada, kalau tidak
                // jatuh ke file logo lokal (yang di-upload di Setting CBT).
                'logo_url'       => $branding['logo'] ?? ($localLogo ? Storage::disk('public')->url($localLogo) : null),
                'favicon_url'    => $branding['favicon'] ?? ($localFav ? Storage::disk('public')->url($localFav) : null),
                // Sama seperti logo_url: pakai punya Data Center bila ada,
                // fallback ke file lokal lama kalau Data Center tak terjangkau.
                'login_bg_url'   => $branding['login_bg'] ?? ($localLoginBg ? Storage::disk('public')->url($localLoginBg) : null),
                'login_title'    => AppSetting::get('login_title', 'Selamat datang di platform CBT Modern sekolah Anda.'),
                'login_subtitle' => AppSetting::get('login_subtitle', 'Kelola data guru, siswa, kelas, dan ujian online dalam satu dashboard yang cepat, aman, dan mudah digunakan.'),
                'footer_text'    => AppSetting::get('footer_text'),
            ]);
        });
    }

    /**
     * Ambil branding dari Data Center secara langsung (tanpa cache) di setiap
     * request, supaya perubahan logo/background di Data Center langsung
     * kelihatan di CBT. Baca langsung dari database Data Center (koneksi
     * 'mysql_datacenter', sama seperti Sekolah/Guru/Siswa) — tidak lagi lewat
     * HTTP API, jadi tidak ada lagi timeout/dependensi ke Data Center harus
     * "hidup" sebagai web server hanya untuk menampilkan branding.
     * Fallback ke [] kalau query gagal (mis. Data Center DB tak terjangkau),
     * sehingga view tetap jatuh ke pengaturan lokal — bukan blank/error.
     */
    protected function datacenterBranding(): array
    {
        try {
            $sekolah = Sekolah::first();
            if (! $sekolah) {
                return [];
            }

            $baseUrl = rtrim((string) config('services.datacenter.app_url'), '/');
            $logoUrl = $sekolah->logo ? $baseUrl.'/storage/'.$sekolah->logo : null;
            $loginBg = DatacenterAppSetting::get('login_bg');

            return [
                'school_name' => $sekolah->nama_sekolah,
                'logo'        => $logoUrl,
                'favicon'     => $logoUrl,
                'login_bg'    => $loginBg ? $baseUrl.'/storage/'.$loginBg : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Gagal mengambil branding Data Center', ['error' => $e->getMessage()]);
            return [];
        }
    }

    protected function defaults(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_tagline' => 'Sistem Informasi Sekolah Terintegrasi',
            'theme_color' => '#0d9488',
            'logo' => null, 'favicon' => null, 'logo_url' => null, 'favicon_url' => null, 'login_bg_url' => null,
            'login_title' => 'Selamat datang di platform CBT Modern sekolah Anda.',
            'login_subtitle' => 'Kelola data guru, siswa, kelas, dan ujian online dalam satu dashboard yang cepat, aman, dan mudah digunakan.',
            'footer_text' => null,
        ];
    }
}
