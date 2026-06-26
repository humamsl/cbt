<?php

namespace App\Models;

use App\Concerns\HasRbac;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRbac;

    protected $fillable = [
        'name', 'email', 'password', 'nomor_hp', 'role', 'foto',
        'role_id', 'account_status', 'is_aktif', 'otp_enabled', 'otp_method',
        'last_seen_at', 'failed_login_count', 'locked_until',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
            'locked_until' => 'datetime',
            'otp_enabled' => 'boolean',
            'is_aktif' => 'boolean',
        ];
    }

    public function otpCodes(): MorphMany
    {
        return $this->morphMany(OtpCode::class, 'authable');
    }

    public function getUserTypeAttribute(): string
    {
        return $this->role ?: 'admin';
    }

    public function getUserNameAttribute(): string
    {
        return $this->email;
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        return $this->foto
            ? asset('storage/'.$this->foto)
            : 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=1f47f5&color=fff';
    }
}
