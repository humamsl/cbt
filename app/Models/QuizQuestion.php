<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $guarded = ['id'];

    public function quiz() { return $this->belongsTo(Quiz::class); }
    public function question() { return $this->belongsTo(Question::class); }
}
