<?php

namespace App\Console\Commands;

use App\Support\LicenseManager;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * SISI OWNER: terbitkan lisensi bertanda tangan untuk satu deployment.
 *
 * Contoh:
 *   php artisan license:issue --domain=smpn40.cyberedugarage.id
 *   php artisan license:issue --domain="*.cyberedugarage.id" --expires=2027-12-31
 *   php artisan license:issue --domain=sekolahX.id --machine=<fingerprint> --expires=unlimited
 *
 * Butuh kunci privat owner (config license.private_key_path atau --key).
 */
class LicenseIssue extends Command
{
    protected $signature = 'license:issue
        {--domain=* : Domain yang diizinkan (boleh berkali-kali; dukung wildcard *.contoh.id)}
        {--machine= : Sidik jari mesin (opsional; ikat lisensi ke 1 server)}
        {--app= : Slug aplikasi target (default: * = berlaku untuk semua app owner)}
        {--expires=unlimited : Tanggal kadaluwarsa YYYY-MM-DD atau "unlimited"}
        {--key= : Path kunci privat (default dari config license.private_key_path)}';

    protected $description = 'SISI OWNER: buat lisensi bertanda tangan untuk domain/mesin tertentu';

    public function handle(): int
    {
        $domains = array_values(array_filter(array_map('trim', (array) $this->option('domain'))));
        $machine = trim((string) $this->option('machine'));

        if (empty($domains) && $machine === '') {
            $this->error('Wajib menentukan minimal satu --domain atau --machine, agar lisensi terikat.');

            return self::INVALID;
        }

        $expires = trim((string) $this->option('expires')) ?: 'unlimited';
        if ($expires !== 'unlimited') {
            try {
                $expires = Carbon::parse($expires)->format('Y-m-d');
            } catch (\Throwable $e) {
                $this->error('Format --expires tidak valid. Gunakan YYYY-MM-DD atau "unlimited".');

                return self::INVALID;
            }
        }

        $keyPath = $this->option('key') ?: config('license.private_key_path');
        if (! $keyPath || ! is_file($keyPath)) {
            $this->error('Kunci privat tidak ditemukan: '.($keyPath ?: '(kosong)'));
            $this->line('Jalankan license:keygen dulu, atau set --key / LICENSE_PRIVATE_KEY.');

            return self::FAILURE;
        }

        $claims = [
            'owner'     => (string) config('license.owner'),
            'app'       => trim((string) $this->option('app')) ?: '*',
            'domains'   => $domains,
            'machine'   => $machine,
            'issued_at' => now()->format('Y-m-d'),
            'expires'   => $expires,
        ];

        try {
            $license = LicenseManager::issue($claims, (string) file_get_contents($keyPath));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('=== Lisensi berhasil diterbitkan ===');
        $this->line('Owner    : '.$claims['owner']);
        $this->line('App      : '.$claims['app']);
        $this->line('Domain   : '.(empty($domains) ? '(tidak diikat domain)' : implode(', ', $domains)));
        $this->line('Machine  : '.($machine !== '' ? $machine : '(tidak diikat mesin)'));
        $this->line('Expires  : '.$expires);
        $this->newLine();
        $this->comment('Pasang di server target dengan:');
        $this->line('  php artisan license:install "'.$license.'"');
        $this->newLine();
        $this->line($license);

        return self::SUCCESS;
    }
}
