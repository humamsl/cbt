<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OtpCode extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function authable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return ! is_null($this->used_at);
    }

    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
