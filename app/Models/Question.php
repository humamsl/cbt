<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function type() { return $this->belongsTo(QuestionType::class, 'question_type_id'); }
    public function mapel() { return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id'); }
    public function topic() { return $this->belongsTo(Topic::class); }
    public function options() { return $this->hasMany(QuestionOption::class)->orderBy('order'); }
    public function correctOptions() { return $this->options()->where('is_correct', true); }
    public function quizQuestions() { return $this->hasMany(QuizQuestion::class); }
    public function creator() { return $this->belongsTo(Guru::class, 'created_by_guru_id'); }
}
