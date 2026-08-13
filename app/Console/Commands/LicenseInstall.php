<?php

namespace App\Console\Commands;

use App\Support\LicenseManager;
use Illuminate\Console\Command;

class LicenseInstall extends Command
{
    protected $signature = 'license:install {license : String lisensi dari license:issue}';

    protected $description = 'Pasang lisensi anti-kloning pada server ini';

    public function handle(): int
    {
        $license = trim((string) $this->argument('license'));
        if ($license === '' || ! str_contains($license, '.')) {
            $this->error('String lisensi tidak valid.');

            return self::INVALID;
        }

        if (! LicenseManager::store($license)) {
            $this->error('Gagal menulis file lisensi ke '.LicenseManager::file());

            return self::FAILURE;
        }

        $this->info('✔ Lisensi tersimpan di '.LicenseManager::file());

        $result = LicenseManager::verify(LicenseManager::currentHost());
        if ($result['ok']) {
            $this->info('✔ Verifikasi: '.$result['reason']);

            return self::SUCCESS;
        }

        $this->warn('⚠ Lisensi terpasang TAPI belum lolos verifikasi untuk server ini:');
        $this->line('   '.$result['reason']);
        $this->line('   Domain server : '.LicenseManager::currentHost());
        $this->line('   Sidik jari    : '.LicenseManager::fingerprint());
        $this->line('   Pastikan lisensi diterbitkan untuk domain/mesin yang benar.');

        return self::FAILURE;
    }
}
