<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

class LicenseManager
{
    private static ?array $cache = null;

    public static function file(): string
    {
        return storage_path('app/license.lic');
    }

    public static function read(): ?string
    {
        $f = self::file();
        if (! is_file($f)) {
            return null;
        }
        $c = trim((string) @file_get_contents($f));

        return $c !== '' ? $c : null;
    }

    public static function store(string $license): bool
    {
        $f = self::file();
        @mkdir(dirname($f), 0775, true);

        return file_put_contents($f, trim($license).PHP_EOL) !== false;
    }

    public static function fingerprint(): string
    {
        $parts = [];

        foreach (['/etc/machine-id', '/var/lib/dbus/machine-id'] as $f) {
            if (is_readable($f)) {
                $id = trim((string) @file_get_contents($f));
                if ($id !== '') {
                    $parts[] = $id;
                    break;
                }
            }
        }

        foreach (glob('/sys/class/net/*/address') ?: [] as $macFile) {
            if (basename(dirname($macFile)) === 'lo') {
                continue;
            }
            $mac = trim((string) @file_get_contents($macFile));
            if ($mac !== '' && $mac !== '00:00:00:00:00:00') {
                $parts[] = $mac;
                break;
            }
        }

        if (is_readable('/sys/class/dmi/id/product_uuid')) {
            $uuid = trim((string) @file_get_contents('/sys/class/dmi/id/product_uuid'));
            if ($uuid !== '') {
                $parts[] = $uuid;
            }
        }

        if (empty($parts)) {
            $parts[] = php_uname('n');
            $parts[] = php_uname('s').php_uname('m');
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 32);
    }

    public static function currentHost(?Request $request = null): string
    {
        try {
            $r = $request ?: request();
            if ($r && $r->getHost()) {
                return $r->getHost();
            }
        } catch (\Throwable $e) {
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host ?: (gethostname() ?: 'localhost');
    }

    public static function issue(array $claims, string $privatePem): string
    {
        $key = openssl_pkey_get_private($privatePem);
        if (! $key) {
            throw new \RuntimeException('Kunci privat tidak valid / tidak bisa dibaca.');
        }

        $payload = json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $p64 = self::b64urlEncode($payload);

        if (! openssl_sign($p64, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Gagal menandatangani lisensi: '.openssl_error_string());
        }

        return $p64.'.'.self::b64urlEncode($signature);
    }

    public static function verify(?string $host = null): array
    {
        if (! config('license.enforce', true)) {
            return ['ok' => true, 'reason' => 'Penegakan lisensi dimatikan', 'payload' => null];
        }

        $license = self::read();
        if ($license === null) {
            return ['ok' => false, 'reason' => 'Lisensi belum terpasang', 'payload' => null];
        }

        if (! str_contains($license, '.')) {
            return ['ok' => false, 'reason' => 'Format lisensi rusak', 'payload' => null];
        }
        [$p64, $s64] = explode('.', trim($license), 2);

        $pub = openssl_pkey_get_public((string) config('license.public_key'));
        if (! $pub) {
            return ['ok' => false, 'reason' => 'Kunci publik aplikasi tidak valid', 'payload' => null];
        }

        $signature = self::b64urlDecode($s64);
        if (openssl_verify($p64, $signature, $pub, OPENSSL_ALGO_SHA256) !== 1) {
            return ['ok' => false, 'reason' => 'Tanda tangan lisensi tidak sah (bukan dari owner)', 'payload' => null];
        }

        $data = json_decode(self::b64urlDecode($p64), true);
        if (! is_array($data)) {
            return ['ok' => false, 'reason' => 'Isi lisensi rusak', 'payload' => null];
        }

        if (($data['owner'] ?? null) !== config('license.owner')) {
            return ['ok' => false, 'reason' => 'Pemilik lisensi tidak cocok', 'payload' => $data];
        }

        $app = $data['app'] ?? '*';
        if ($app !== '*' && $app !== config('license.app')) {
            return ['ok' => false, 'reason' => 'Lisensi diterbitkan untuk aplikasi lain', 'payload' => $data];
        }

        if (! empty($data['machine']) && ! hash_equals((string) $data['machine'], self::fingerprint())) {
            return ['ok' => false, 'reason' => 'Lisensi terikat ke server/mesin lain', 'payload' => $data];
        }

        $domains = (array) ($data['domains'] ?? []);
        if (! empty($domains)) {
            $host = $host ?: self::currentHost();
            if (! self::hostMatches($host, $domains)) {
                return ['ok' => false, 'reason' => 'Domain "'.$host.'" tidak diizinkan oleh lisensi', 'payload' => $data];
            }
        }

        $exp = $data['expires'] ?? 'unlimited';
        if ($exp !== 'unlimited' && $exp) {
            try {
                if (Carbon::parse($exp)->endOfDay()->isPast()) {
                    return ['ok' => false, 'reason' => 'Masa berlaku lisensi habis ('.$exp.')', 'payload' => $data];
                }
            } catch (\Throwable $e) {
                return ['ok' => false, 'reason' => 'Tanggal kadaluwarsa lisensi tidak valid', 'payload' => $data];
            }
        }

        return ['ok' => true, 'reason' => 'Lisensi valid', 'payload' => $data];
    }

    public static function check(?string $host = null): array
    {
        return self::$cache ??= self::verify($host);
    }

    public static function passes(?string $host = null): bool
    {
        return self::check($host)['ok'];
    }

    protected static function hostMatches(string $host, array $patterns): bool
    {
        $host = strtolower(trim($host));
        foreach ($patterns as $p) {
            $p = strtolower(trim((string) $p));
            if ($p === '') {
                continue;
            }
            if ($p === $host) {
                return true;
            }
            if (str_starts_with($p, '*.')) {
                $base = substr($p, 2);
                if ($host === $base || str_ends_with($host, '.'.$base)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected static function b64urlEncode(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    protected static function b64urlDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }
}
