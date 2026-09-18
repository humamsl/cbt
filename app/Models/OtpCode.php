<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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

    /**
     * Buat kode OTP baru untuk login (dipakai bersama oleh OtpController
     * (web, session-based) & Api\AuthController (mobile, tiket cache) supaya
     * cara pembuatan kodenya tidak menyimpang antara dua jalur login).
     * Return [OtpCode $row, string $plainCode] -- plain code TIDAK disimpan
     * di kolom manapun (cuma hash-nya), jadi pemanggil yang bertanggung jawab
     * mengirim/menampilkannya sesuai medium masing-masing.
     */
    public static function generateFor($user, ?string $ip): array
    {
        $plain = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $destination = $user->email ?? $user->nomor_hp ?? null;

        $otp = static::create([
            'authable_type' => $user::class,
            'authable_id'   => $user->id,
            'code'          => Hash::make($plain),
            'purpose'       => 'login',
            'channel'       => $user->otp_method ?? 'email',
            'destination'   => $destination,
            'expires_at'    => now()->addMinutes(5),
            'ip_address'    => $ip,
        ]);

        // Kirim OTP — di production sambungkan ke driver mail / WA Gateway.
        // Untuk development: tulis ke log supaya masih bisa dites tanpa gateway asli.
        Log::info('OTP CBT', ['user_id' => $user->id, 'kode' => $plain, 'tujuan' => $destination]);

        return [$otp, $plain];
    }

    /** Kode aktif (belum dipakai & belum kedaluwarsa) terbaru untuk login user ini, kalau ada. */
    public static function activeLoginFor($user): ?self
    {
        return static::where('authable_type', $user::class)
            ->where('authable_id', $user->id)
            ->where('purpose', 'login')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
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
