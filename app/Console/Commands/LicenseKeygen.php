<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * SISI OWNER: buat pasangan kunci RSA sekali saja.
 *
 * - private.pem  -> SIMPAN OFFLINE, jangan pernah di-deploy/di-commit.
 * - public.pem   -> tempel isinya ke config/license.php ('public_key').
 *
 * Siapa pun yang memegang private.pem bisa menerbitkan lisensi, jadi jaga
 * kerahasiaannya. Jalankan ulang perintah ini hanya bila ingin mengganti
 * seluruh kunci (semua lisensi lama otomatis tidak berlaku).
 */
class LicenseKeygen extends Command
{
    protected $signature = 'license:keygen
        {--dir= : Folder tujuan menyimpan private.pem & public.pem (default: <root>/license-keys)}
        {--bits=4096 : Ukuran kunci RSA}
        {--force : Timpa file kunci yang sudah ada}';

    protected $description = 'SISI OWNER: generate pasangan kunci RSA untuk menandatangani lisensi';

    public function handle(): int
    {
        $dir = $this->option('dir') ?: base_path('../license-keys');
        $priv = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'private.pem';
        $pub  = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'public.pem';

        if ((is_file($priv) || is_file($pub)) && ! $this->option('force')) {
            $this->error('Kunci sudah ada di '.$dir.'. Pakai --force untuk menimpa (HATI-HATI: lisensi lama akan hangus).');

            return self::FAILURE;
        }

        @mkdir($dir, 0700, true);

        $opts = ['private_key_bits' => (int) $this->option('bits'), 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if ($cnf = $this->opensslConfig()) {
            $opts['config'] = $cnf;
        }

        $res = openssl_pkey_new($opts);
        if (! $res) {
            $this->error('Gagal generate kunci: '.openssl_error_string());
            $this->line('Di Windows, pastikan openssl.cnf terbaca (set OPENSSL_CONF atau php extras/ssl/openssl.cnf).');

            return self::FAILURE;
        }

        openssl_pkey_export($res, $privatePem, null, $opts);
        $publicPem = openssl_pkey_get_details($res)['key'];

        file_put_contents($priv, $privatePem);
        @chmod($priv, 0600);
        file_put_contents($pub, $publicPem);

        $this->info('✔ Pasangan kunci dibuat:');
        $this->line('  privat : '.$priv.'  (SIMPAN OFFLINE, JANGAN di-deploy)');
        $this->line('  publik : '.$pub);
        $this->newLine();
        $this->comment('Tempel blok berikut ke config/license.php pada bagian "public_key":');
        $this->newLine();
        $this->line($publicPem);

        return self::SUCCESS;
    }

    /** Cari openssl.cnf yang bisa dipakai (khusus Windows/dev). */
    protected function opensslConfig(): ?string
    {
        if (($env = getenv('OPENSSL_CONF')) && is_file($env)) {
            return $env;
        }
        foreach ([
            dirname(PHP_BINARY).'/extras/ssl/openssl.cnf',
            dirname(PHP_BINARY).'/extras/openssl/openssl.cnf',
        ] as $c) {
            if (is_file($c)) {
                return $c;
            }
        }

        return null;
    }
}
