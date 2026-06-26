<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionOption extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function question() { return $this->belongsTo(Question::class); }
}
