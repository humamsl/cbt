<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttemptAnswer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_marked' => 'boolean',
        'is_correct' => 'boolean',
        'answer_json' => 'array',
        'partial_score' => 'float',
    ];

    public function attempt() { return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id'); }
    public function quizQuestion() { return $this->belongsTo(QuizQuestion::class); }
    public function option() { return $this->belongsTo(QuestionOption::class, 'question_option_id'); }

    /** PGK: id opsi yang dicentang siswa. */
    public function selectedOptionIds(): array
    {
        return array_map('intval', (array) ($this->answer_json ?? []));
    }

    /** Penjodohan: peta [left_option_id => right_option_id pilihan siswa]. */
    public function matchPairs(): array
    {
        $pairs = (array) ($this->answer_json ?? []);
        $out = [];
        foreach ($pairs as $leftId => $rightId) {
            $out[(int) $leftId] = (int) $rightId;
        }
        return $out;
    }
}
