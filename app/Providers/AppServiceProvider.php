<?php

namespace App\Providers;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
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
            $view->with('AppCfg', [
                'app_name'       => AppSetting::get('app_name', config('app.name')),
                'app_tagline'    => AppSetting::get('app_tagline', 'Sistem Informasi Sekolah Terintegrasi'),
                'theme_color'    => AppSetting::get('theme_color', '#1f47f5'),
                'logo'           => AppSetting::get('logo'),
                'favicon'        => AppSetting::get('favicon'),
                'login_bg'       => AppSetting::get('login_bg'),
                'login_title'    => AppSetting::get('login_title', 'Selamat datang di platform CBT Modern sekolah Anda.'),
                'login_subtitle' => AppSetting::get('login_subtitle', 'Kelola data guru, siswa, kelas, dan ujian online dalam satu dashboard yang cepat, aman, dan mudah digunakan.'),
                'footer_text'    => AppSetting::get('footer_text'),
            ]);
        });
    }

    protected function defaults(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_tagline' => 'Sistem Informasi Sekolah Terintegrasi',
            'theme_color' => '#1f47f5',
            'logo' => null, 'favicon' => null, 'login_bg' => null,
            'login_title' => 'Selamat datang di platform CBT Modern sekolah Anda.',
            'login_subtitle' => 'Kelola data guru, siswa, kelas, dan ujian online dalam satu dashboard yang cepat, aman, dan mudah digunakan.',
            'footer_text' => null,
        ];
    }
}
