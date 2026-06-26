<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionType extends Model
{
    protected $guarded = ['id'];

    public function questions() { return $this->hasMany(Question::class); }
}
