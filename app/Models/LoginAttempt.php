<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'success' => 'boolean',
    ];

    public function getDeviceBadgeAttribute(): string
    {
        return [
            'desktop' => 'badge-info',
            'mobile'  => 'badge-warning',
            'tablet'  => 'badge-brand',
        ][$this->device_type] ?? 'badge-muted';
    }
}
