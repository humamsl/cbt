<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MataPelajaran extends Model
{
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
