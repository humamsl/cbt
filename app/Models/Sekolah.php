<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sekolah extends Model
{
    protected $table = 'sekolah';
    protected $guarded = ['id'];

    public function getLogoUrlAttribute(): string
    {
        return $this->logo
            ? asset('storage/'.$this->logo)
            : asset('img/logo-default.svg');
    }
}
