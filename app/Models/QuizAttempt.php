<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizAttempt extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_done' => 'boolean',
        'is_blocked' => 'boolean',
        'is_force_submitted' => 'boolean',
        'time_start' => 'datetime',
        'time_end' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function getStatusAttribute(): string
    {
        if ($this->is_blocked) return 'blokir';
        if ($this->is_done) return 'selesai';
        if ($this->time_start) return 'sedang';
        return 'belum';
    }

    public function getStatusBadgeAttribute(): string
    {
        return [
            'belum'  => 'badge-muted',
            'sedang' => 'badge-info',
            'blokir' => 'badge-danger',
            'selesai'=> 'badge-success',
        ][$this->status] ?? 'badge-muted';
    }

    public function quiz() { return $this->belongsTo(Quiz::class); }
    public function siswa() { return $this->belongsTo(Siswa::class); }
    public function answers() { return $this->hasMany(QuizAttemptAnswer::class); }
    public function violations() { return $this->hasMany(ExamViolation::class); }
}
