<?php

namespace App\Http\Controllers\Api;

use App\Concerns\LogsLoginAttempts;
use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login untuk APLIKASI CBT (siswa) — mereplikasi lockout/rate-limit dari
 * App\Http\Controllers\AuthController (web), tapi:
 * - Tanpa session/guard Laravel sama sekali (kredensial dicek manual) --
 *   route API tidak lewat middleware 'web', jadi Auth::guard()->attempt()
 *   (berbasis session) tidak relevan di sini.
 * - Verifikasi kredensial → (kalau otp_enabled) OTP → BARU terbit token
 *   Sanctum. Beda dari web yang menerbitkan sesi dulu baru menahan akses
 *   lewat middleware OtpVerification -- di sini token akses sengaja TIDAK
 *   pernah diberikan sebelum OTP lolos.
 * - "Satu akun satu perangkat" diganti dari SingleSessionGuard (berbasis
 *   session ID cookie, tidak berlaku untuk klien token) menjadi: setiap
 *   login sukses mencabut SEMUA token lama siswa itu. Sanctum otomatis
 *   membalas 401 di perangkat lama pada request berikutnya begitu token-nya
 *   sudah dihapus dari database -- tidak perlu middleware kustom.
 */
class AuthController extends Controller
{
    use LogsLoginAttempts;

    public const MAX_ATTEMPTS = 5;
    public const LOCK_MINUTES = 15;

    public function login(Request $r)
    {
        $data = $r->validate([
            'nisn'     => 'required|string|max:100',
            'password' => 'required|string|max:100',
        ]);

        $rateKey = 'login:api:'.$r->ip().':'.$data['nisn'];
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $seconds = RateLimiter::availableIn($rateKey);
            throw ValidationException::withMessages([
                'nisn' => "Terlalu banyak percobaan dari perangkat ini. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $siswa = Siswa::where('nisn', $data['nisn'])->first();

        if ($siswa && $siswa->locked_until && $siswa->locked_until->isFuture()) {
            $this->logAttempt($r, $data['nisn'], 'siswa', false);
            $minutes = now()->diffInMinutes($siswa->locked_until);
            throw ValidationException::withMessages([
                'nisn' => "Akun dikunci sementara. Coba lagi dalam {$minutes} menit.",
            ]);
        }

        $ok = $siswa && Hash::check($data['password'], (string) $siswa->password);

        $this->logAttempt($r, $data['nisn'], 'siswa', $ok);
        RateLimiter::hit($rateKey, 60 * 10);

        if (! $ok) {
            if ($siswa) {
                $count = (int) $siswa->failed_login_count + 1;
                $updates = ['failed_login_count' => $count];
                if ($count >= self::MAX_ATTEMPTS) {
                    $updates['locked_until'] = now()->addMinutes(self::LOCK_MINUTES);
                    $updates['failed_login_count'] = 0;
                }
                $siswa->forceFill($updates)->save();
            }
            throw ValidationException::withMessages([
                'nisn' => 'NISN atau password salah.',
            ]);
        }

        if (strtolower((string) $siswa->account_status) !== 'active') {
            throw ValidationException::withMessages([
                'nisn' => 'Akun tidak aktif. Hubungi admin sekolah.',
            ]);
        }

        RateLimiter::clear($rateKey);
        $siswa->forceFill(['failed_login_count' => 0, 'locked_until' => null, 'last_seen_at' => now()])->save();

        if ($siswa->otp_enabled) {
            return response()->json([
                'otp_required' => true,
                'otp_token'    => $this->issueOtpTicket($siswa, $r),
            ]);
        }

        return response()->json($this->issueToken($siswa, $r));
    }

    public function verifyOtp(Request $r)
    {
        $data = $r->validate([
            'otp_token' => 'required|string',
            'code'      => 'required|string|min:4|max:8',
        ]);

        $siswaId = Cache::get('otp_login:'.$data['otp_token']);
        if (! $siswaId) {
            throw ValidationException::withMessages([
                'code' => 'Sesi OTP tidak valid atau sudah kedaluwarsa. Silakan login ulang.',
            ]);
        }

        $siswa = Siswa::find($siswaId);
        abort_unless($siswa, 404);

        $otp = OtpCode::activeLoginFor($siswa);
        if (! $otp || ! Hash::check($data['code'], $otp->code)) {
            throw ValidationException::withMessages([
                'code' => 'Kode OTP salah atau sudah kedaluwarsa.',
            ]);
        }

        $otp->markUsed();
        Cache::forget('otp_login:'.$data['otp_token']);

        return response()->json($this->issueToken($siswa, $r));
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        return response()->json(['ok' => true]);
    }

    public function me(Request $r)
    {
        $siswa = $r->user();
        return response()->json([
            'id'   => $siswa->id,
            'nisn' => $siswa->nisn,
            'nama' => $siswa->nama_siswa,
        ]);
    }

    /** Buat kode OTP (sumber sama dgn OtpController web, lihat OtpCode::generateFor) lalu simpan tiket sementara di cache -- token akses BELUM diterbitkan. */
    protected function issueOtpTicket(Siswa $siswa, Request $r): string
    {
        OtpCode::generateFor($siswa, $r->ip());

        $ticket = Str::random(40);
        Cache::put('otp_login:'.$ticket, $siswa->id, now()->addMinutes(5));

        return $ticket;
    }

    /** Satu akun = satu perangkat: cabut semua token lama sebelum menerbitkan yang baru. */
    protected function issueToken(Siswa $siswa, Request $r): array
    {
        $siswa->tokens()->delete();
        $token = $siswa->createToken('aplikasi-cbt-siswa')->plainTextToken;
        $siswa->forceFill(['current_device' => substr((string) $r->userAgent(), 0, 100)])->save();

        return [
            'token' => $token,
            'siswa' => [
                'id'   => $siswa->id,
                'nisn' => $siswa->nisn,
                'nama' => $siswa->nama_siswa,
            ],
        ];
    }
}
