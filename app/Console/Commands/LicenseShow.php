<?php

namespace App\Console\Commands;

use App\Support\LicenseManager;
use Illuminate\Console\Command;

/**
 * Tampilkan identitas server (domain + sidik jari) dan status lisensi.
 * Berguna saat meminta lisensi ke owner, atau mengecek instalasi.
 */
class LicenseShow extends Command
{
    protected $signature = 'license:show';

    protected $description = 'Tampilkan sidik jari mesin, domain, dan status lisensi';

    public function handle(): int
    {
        $host = LicenseManager::currentHost();
        $fp = LicenseManager::fingerprint();
        $result = LicenseManager::verify($host);

        $this->info('=== Status Lisensi ('.config('license.app').') ===');
        $this->line('Domain (APP_URL) : '.$host);
        $this->line('Sidik jari mesin : '.$fp);
        $this->line('File lisensi     : '.LicenseManager::file().(LicenseManager::read() ? ' (ada)' : ' (belum ada)'));
        $this->newLine();

        if ($result['ok']) {
            $this->info('STATUS: VALID — '.$result['reason']);
        } else {
            $this->error('STATUS: TERKUNCI — '.$result['reason']);
        }

        if (! empty($result['payload'])) {
            $p = $result['payload'];
            $this->newLine();
            $this->line('Isi lisensi:');
            $this->line('  owner   : '.($p['owner'] ?? '-'));
            $this->line('  app     : '.($p['app'] ?? '-'));
            $this->line('  domains : '.(empty($p['domains']) ? '-' : implode(', ', (array) $p['domains'])));
            $this->line('  machine : '.(($p['machine'] ?? '') !== '' ? $p['machine'] : '-'));
            $this->line('  expires : '.($p['expires'] ?? '-'));
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
