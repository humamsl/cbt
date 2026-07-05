<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MataPelajaran extends Model
{
    /** Baca/tulis langsung ke database Data Center (sumber tunggal), real-time. */
    protected $connection = 'mysql_datacenter';
    protected $table = 'mata_pelajaran';
    protected $guarded = ['id'];

    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class);
    }

    public function guruMapel()
    {
        return $this->hasMany(GuruMapel::class);
    }

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }
}
