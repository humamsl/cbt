<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sekolah extends Model
{
    /** Baca/tulis langsung ke database Data Center (sumber tunggal), real-time. */
    protected $connection = 'mysql_datacenter';
    protected $table = 'sekolah';
    protected $guarded = ['id'];

    public function getLogoUrlAttribute(): string
    {
        return $this->logo
            ? asset('storage/'.$this->logo)
            : asset('img/logo-default.svg');
    }
}
