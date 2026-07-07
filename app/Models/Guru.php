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

    /**
     * Baca/tulis langsung ke database Data Center (sumber tunggal), real-time —
     * termasuk autentikasi login (password guru TIDAK disimpan/dicek di CBT,
     * cek langsung ke baris asli di Data Center via koneksi ini).
     */
    protected $connection = 'mysql_datacenter';
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

    /** Apakah akun sedang terkunci karena terlalu banyak percobaan login gagal. */
    public function getIsTerkunciAttribute(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->foto ? asset('storage/'.$this->foto) : null;
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
