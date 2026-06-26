<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Topic extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function mapel() { return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id'); }
    public function rombel() { return $this->belongsTo(RombonganBelajar::class, 'rombongan_belajar_id'); }
    public function createdBy() { return $this->belongsTo(Guru::class, 'created_by_guru_id'); }
    public function questions() { return $this->hasMany(Question::class); }
}
