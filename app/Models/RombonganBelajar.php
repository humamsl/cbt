<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RombonganBelajar extends Model
{
    protected $table = 'rombongan_belajar';
    protected $guarded = ['id'];

    public function jurusan() { return $this->belongsTo(Jurusan::class); }
    public function tahunAjaran() { return $this->belongsTo(TahunAjaran::class); }
    public function waliKelas() { return $this->belongsTo(Guru::class, 'wali_kelas_id'); }

    public function siswaRombel() { return $this->hasMany(SiswaRombel::class); }
    public function siswa()
    {
        return $this->belongsToMany(Siswa::class, 'siswa_rombel');
    }

    public function guruMapel() { return $this->hasMany(GuruMapel::class); }
}
