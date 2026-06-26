<?php

namespace App\Models;

use App\Concerns\HasRbac;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Siswa extends Authenticatable
{
    use HasFactory, Notifiable, HasRbac;

    protected $table = 'siswa';
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

    public function username(): string { return 'nisn'; }

    public function getUserTypeAttribute(): string { return 'siswa'; }
    public function getNameAttribute(): ?string { return $this->attributes['nama_siswa'] ?? null; }
    public function getUserNameAttribute(): string { return $this->nisn; }

    public function getProfilePhotoUrlAttribute(): string
    {
        return $this->foto
            ? asset('storage/'.$this->foto)
            : 'https://ui-avatars.com/api/?name='.urlencode($this->nama_siswa).'&background=f59e0b&color=fff';
    }

    public function rombelSekarang()
    {
        return $this->hasOne(SiswaRombel::class)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true));
    }

    public function siswaRombel()
    {
        return $this->hasMany(SiswaRombel::class);
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
