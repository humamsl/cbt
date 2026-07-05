<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TingkatKelas extends Model
{
    /** Baca/tulis langsung ke database Data Center (sumber tunggal), real-time. */
    protected $connection = 'mysql_datacenter';
    protected $table = 'tingkat_kelas';
    protected $guarded = ['id'];

    protected $casts = ['is_aktif' => 'boolean'];

    public function scopeAktif($q)
    {
        return $q->where('is_aktif', true);
    }

    /** Helper: dropdown options siap pakai (nomor => nama) */
    public static function dropdown(): array
    {
        return static::aktif()->orderBy('urutan')->orderBy('nomor')
            ->pluck('nama', 'nomor')->toArray();
    }
}
