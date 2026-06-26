<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuruMapel extends Model
{
    protected $table = 'guru_mapel';
    protected $guarded = ['id'];

    public function guru() { return $this->belongsTo(Guru::class); }
    public function mapel() { return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id'); }
    public function rombel() { return $this->belongsTo(RombonganBelajar::class, 'rombongan_belajar_id'); }
    public function tahunAjaran() { return $this->belongsTo(TahunAjaran::class); }
}
