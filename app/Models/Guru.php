<?php

namespace App\Models;

use App\Concerns\HasRbac;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Guru extends Authenticatable
{
    use HasFactory, Notifiable, HasRbac;

    protected $table = 'guru';
    protected $guarded = ['id'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_aktif' => 'boolean',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
            'locked_until' => 'datetime',
            'otp_enabled' => 'boolean',
        ];
    }

    public function otpCodes(): MorphMany
    {
        return $this->morphMany(OtpCode::class, 'authable');
    }

    public function getUserTypeAttribute(): string { return 'guru'; }
    public function getNameAttribute(): ?string { return $this->attributes['nama_ptk'] ?? null; }
    public function getUserNameAttribute(): string { return $this->nip; }

    public function getProfilePhotoUrlAttribute(): string
    {
        return $this->foto
            ? asset('storage/'.$this->foto)
            : 'https://ui-avatars.com/api/?name='.urlencode($this->nama_ptk).'&background=059669&color=fff';
    }

    public function guruMapel()
    {
        return $this->hasMany(GuruMapel::class);
    }

    public function rombelWali()
    {
        return $this->hasMany(RombonganBelajar::class, 'wali_kelas_id');
    }
}
