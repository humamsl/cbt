<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurusan extends Model
{
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
