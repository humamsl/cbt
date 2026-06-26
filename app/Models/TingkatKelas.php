<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TingkatKelas extends Model
{
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
