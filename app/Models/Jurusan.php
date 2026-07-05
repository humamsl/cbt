<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurusan extends Model
{
    /** Baca/tulis langsung ke database Data Center (sumber tunggal), real-time. */
    protected $connection = 'mysql_datacenter';
    protected $table = 'jurusan';
    protected $guarded = ['id'];

    public function rombel()
    {
        return $this->hasMany(RombonganBelajar::class);
    }

    public function mapel()
    {
        return $this->hasMany(MataPelajaran::class);
    }
}
