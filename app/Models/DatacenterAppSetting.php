<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Baca langsung tabel `app_settings` milik Data Center (sumber tunggal),
 * lewat koneksi 'mysql_datacenter' — sama seperti Sekolah, Guru, Siswa, dst.
 *
 * Sengaja TIDAK di-cache (beda dengan App\Models\AppSetting lokal): perubahan
 * di menu Pengaturan Aplikasi Data Center (mis. background halaman login)
 * harus langsung kelihatan di CBT tanpa delay, sama seperti perilaku lama
 * saat masih lewat DatacenterClient::branding().
 */
class DatacenterAppSetting extends Model
{
    protected $connection = 'mysql_datacenter';
    protected $table = 'app_settings';
    protected $guarded = ['id'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::where('key', $key)->first();
        if (! $row) {
            return $default;
        }

        return $row->type === 'json' ? json_decode((string) $row->value, true) : $row->value;
    }
}
