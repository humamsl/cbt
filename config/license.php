<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proteksi anti-kloning / pembajakan (lisensi bertanda tangan)
    |--------------------------------------------------------------------------
    |
    | Lisensi ditandatangani dengan KUNCI PRIVAT milik owner (offline). Aplikasi
    | hanya menyimpan KUNCI PUBLIK untuk verifikasi. Karena hanya owner yang
    | punya kunci privat, hanya owner yang bisa MENERBITKAN lisensi yang sah —
    | jadi hasil kloning server ke domain/mesin lain tidak akan bisa dijalankan
    | tanpa lisensi baru yang hanya bisa dibuat owner.
    |
    | Lihat: App\Support\LicenseManager, App\Http\Middleware\CheckLicense,
    | serta perintah artisan license:keygen / license:issue / license:install /
    | license:show.
    |
    */

    // Identitas aplikasi (dipakai saat mencocokkan klaim "app" pada lisensi).
    'app'   => 'cbt',

    // Pemilik sah — harus sama dengan klaim "owner" di dalam lisensi.
    'owner' => 'AnonymouSL',

    // Kunci publik owner (aman disertakan di source). Pasangannya (kunci privat)
    // WAJIB dijaga offline oleh owner dan TIDAK boleh ikut ter-deploy.
    'public_key' => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAlIOMArx5HQECPLxShZ3w
V/opq/4aSAzd6LEaRrpiRW3N9wD0CZt8B5zfucXQHRir7gHcvKCzZjChe6pwaVWW
5SDeOuL3hTPzabvBrBpPUEqDsfipjMEuH97rCZziWRUw2f4gytwNZ36x+jR3USTU
t4vsQgCzgV65ERN8aNwofyZ92ZV34mqtN8Z9EnaPQlxpxnLsV+ikoI3VsArWJogD
AFAYS4NaJDwagz77cb5sXkMRSwnIszAaABkbf8iYa0j8PNEM6X5dKugdd9Xduze+
UgHdwZB11zEiy9kZRNa4KDxFcWoUQfunK8t8tuwB+eHoMofIUmjiaOoionbXWFg0
lcpgQ4YP81bNFggNosbcz9/Zz0poUHxJchFJAOFCjVbcevabgc+UpniYQ9L2tiyQ
dFXpYH/iIdScAh2S95FpR9dvP6Enh/X/Dx102hvlRODcYUrP/Qql4jiBV7oJbl2Z
Ur4vqgjVwGdXqryrHGKwNsLcNceexoMQNLIzYs/AIBeqfM40633w9hEho2mYOkaA
n4ba0+JWFiIzUhlaTmVvchQDTL0NdyW0o9hOSLIMjdDxX4BJBLFtOLbisD5iZu7g
bDq+0uCaFMDvSK4pHjpyBOWLre5XJMUu8dUhw91xW2oef81HkI09RHnYaBNIM6sl
bRtTyupzrP9BpBF5rCmnqCcCAwEAAQ==
-----END PUBLIC KEY-----
PEM,

    // Path kunci privat untuk MENERBITKAN lisensi — HANYA di mesin owner.
    // Default menunjuk ke luar folder app (tidak ikut ter-deploy/clone).
    // Bisa dioverride lewat .env: LICENSE_PRIVATE_KEY=/path/ke/private.pem
    'private_key_path' => env('LICENSE_PRIVATE_KEY', base_path('../license-keys/private.pem')),

    // Kalau false, penegakan lisensi dimatikan (mis. untuk debugging lokal).
    // JANGAN dimatikan di produksi.
    'enforce' => (bool) env('LICENSE_ENFORCE', true),

];
