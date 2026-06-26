<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttemptAnswer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_marked' => 'boolean',
        'is_correct' => 'boolean',
    ];

    public function attempt() { return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id'); }
    public function quizQuestion() { return $this->belongsTo(QuizQuestion::class); }
    public function option() { return $this->belongsTo(QuestionOption::class, 'question_option_id'); }
}
