<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamViolation extends Model
{
    protected $guarded = ['id'];

    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }
}
