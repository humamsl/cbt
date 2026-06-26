<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionToken extends Model
{
    protected $table = 'session_tokens';
    protected $guarded = ['id'];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_upto' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function tahunAjaran() { return $this->belongsTo(TahunAjaran::class); }
}
