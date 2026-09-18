<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum default TIDAK mengunci koneksi model token-nya sendiri -- Eloquent
 * otomatis mewarisi koneksi model PARENT saat token dibuat lewat relasi
 * `tokens()` (lihat HasRelationships::newRelatedInstance()). Siswa hidup di
 * koneksi `mysql_datacenter`, jadi tanpa override ini token yang diterbitkan
 * untuk siswa diam-diam tersimpan di database Data Center (yang KEBETULAN
 * SUDAH punya tabel `personal_access_tokens` sendiri dari project Datacenter
 * yang terpisah) -- bukan di `personal_access_tokens` milik project CBT ini
 * yang baru dimigrasikan. Akibatnya token "berhasil dibuat" tapi validasi
 * berikutnya (Sanctum selalu mencari di koneksi DEFAULT) tidak pernah
 * menemukannya -- APLIKASI SELALU DITOLAK "Unauthenticated" walau baru saja
 * login sukses. Paksa selalu pakai koneksi default project ini.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'mysql';
}
