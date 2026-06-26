<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quiz extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_upto' => 'datetime',
        'is_published' => 'boolean',
        'randomize' => 'boolean',
        'randomize_options' => 'boolean',
        'show_score' => 'boolean',
        'require_session_token' => 'boolean',
        'protection_enabled' => 'boolean',
        'settings' => 'array',
        'target_tingkat' => 'array',
    ];

    public function mapel() { return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id'); }
    public function rombel() { return $this->belongsTo(RombonganBelajar::class, 'rombongan_belajar_id'); }

    /** Multi-rombel target via pivot quiz_rombongan_belajar */
    public function rombelTargets()
    {
        return $this->belongsToMany(RombonganBelajar::class, 'quiz_rombongan_belajar');
    }

    public function tahunAjaran() { return $this->belongsTo(TahunAjaran::class); }
    public function creator() { return $this->belongsTo(Guru::class, 'created_by_guru_id'); }

    public function questions() { return $this->hasMany(QuizQuestion::class)->orderBy('order'); }
    public function attempts() { return $this->hasMany(QuizAttempt::class); }

    public function getStatusAttribute(): string
    {
        if (! $this->is_published) return 'draft';
        $now = Carbon::now();
        if ($this->valid_from && $now->lt($this->valid_from)) return 'menunggu';
        if ($this->valid_upto && $now->gt($this->valid_upto)) return 'selesai';
        return 'berlangsung';
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'badge-muted',
            'menunggu' => 'badge-info',
            'berlangsung' => 'badge-success',
            'selesai' => 'badge-warning',
            default => 'badge-muted',
        };
    }
}
