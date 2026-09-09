<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];
}
