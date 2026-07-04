<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Services\DatacenterClient;
use Illuminate\Support\Facades\Cache;
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

            // Branding (nama + logo + favicon) bersumber dari Data Center supaya
            // seragam dengan landing-page & Data Center itu sendiri. Di-cache,
            // dan fallback ke pengaturan lokal kalau Data Center tak terjangkau.
            $branding  = $this->datacenterBranding();
            $localLogo = AppSetting::get('logo');
            $localFav  = AppSetting::get('favicon');

            $view->with('AppCfg', [
                'app_name'       => $branding['school_name'] ?? AppSetting::get('app_name', config('app.name')),
                'app_tagline'    => AppSetting::get('app_tagline', 'Sistem Informasi Sekolah Terintegrasi'),
                'theme_color'    => AppSetting::get('theme_color', '#0d9488'),
                'logo'           => $localLogo,
                'favicon'        => $localFav,
                // URL siap-pakai: pakai logo Data Center bila ada, kalau tidak
                // jatuh ke file logo lokal (yang di-upload di Setting CBT).
                'logo_url'       => $branding['logo'] ?? ($localLogo ? Storage::url($localLogo) : null),
                'favicon_url'    => $branding['favicon'] ?? ($localFav ? Storage::url($localFav) : null),
                'login_bg'       => AppSetting::get('login_bg'),
                'login_title'    => AppSetting::get('login_title', 'Selamat datang di platform CBT Modern sekolah Anda.'),
                'login_subtitle' => AppSetting::get('login_subtitle', 'Kelola data guru, siswa, kelas, dan ujian online dalam satu dashboard yang cepat, aman, dan mudah digunakan.'),
                'footer_text'    => AppSetting::get('footer_text'),
            ]);
        });
    }

    /**
     * Ambil branding dari Data Center, di-cache 5 menit. Aman kalau Data Center
     * mati/lambat: kembalikan array kosong sehingga view pakai fallback lokal.
     */
    protected function datacenterBranding(): array
    {
        return Cache::remember('branding.datacenter', now()->addMinutes(5), function () {
            try {
                return app(DatacenterClient::class)->branding();
            } catch (\Throwable $e) {
                Log::warning('Gagal mengambil branding Data Center', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    protected function defaults(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_tagline' => 'Sistem Informasi Sekolah Terintegrasi',
            'theme_color' => '#0d9488',
            'logo' => null, 'favicon' => null, 'logo_url' => null, 'favicon_url' => null, 'login_bg' => null,
            'login_title' => 'Selamat datang di platform CBT Modern sekolah Anda.',
            'login_subtitle' => 'Kelola data guru, siswa, kelas, dan ujian online dalam satu dashboard yang cepat, aman, dan mudah digunakan.',
            'footer_text' => null,
        ];
    }
}
