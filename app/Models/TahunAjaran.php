<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TahunAjaran extends Model
{
    /** Baca/tulis langsung ke database Data Center (sumber tunggal), real-time. */
    protected $connection = 'mysql_datacenter';
    protected $table = 'tahun_ajaran';
    protected $guarded = ['id'];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'is_aktif' => 'boolean',
    ];

    public function rombel()
    {
        return $this->hasMany(RombonganBelajar::class);
    }

    public static function aktif(): ?self
    {
        return static::where('is_aktif', true)->latest('id')->first();
    }
}
