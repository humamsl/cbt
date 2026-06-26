<?php

namespace App\Http\Controllers;

use App\Models\OtpCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        // Generate kode baru jika belum ada / sudah kadaluwarsa
        $active = OtpCode::where('authable_type', $user::class)
            ->where('authable_id', $user->id)
            ->where('purpose', 'login')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $active) {
            $active = $this->generate($user, $request);
        }

        return view('auth.verify-otp', [
            'destination' => $this->maskedDestination($user),
            'channel' => $user->otp_method ?? 'email',
            'expiresAt' => $active->expires_at,
        ]);
    }

    public function resend(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $recent = OtpCode::where('authable_type', $user::class)
            ->where('authable_id', $user->id)
            ->where('created_at', '>', now()->subSeconds(60))
            ->exists();

        if ($recent) {
            return back()->with('error', 'Tunggu minimal 60 detik sebelum mengirim ulang kode OTP.');
        }

        $this->generate($user, $request);
        return back()->with('success', 'Kode OTP baru telah dikirim.');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|min:4|max:8']);
        $user = $request->user();
        abort_unless($user, 401);

        $code = OtpCode::where('authable_type', $user::class)
            ->where('authable_id', $user->id)
            ->where('purpose', 'login')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')->first();

        if (! $code || ! Hash::check($request->code, $code->code)) {
            return back()->withErrors(['code' => 'Kode OTP salah atau sudah kadaluwarsa.']);
        }

        $code->markUsed();
        session(['otp_verified_at' => now()->toIso8601String()]);

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Verifikasi 2FA berhasil.');
    }

    protected function generate($user, Request $request): OtpCode
    {
        $plain = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $destination = $user->email ?? $user->nomor_hp ?? null;

        $otp = OtpCode::create([
            'authable_type' => $user::class,
            'authable_id'   => $user->id,
            'code'          => Hash::make($plain),
            'purpose'       => 'login',
            'channel'       => $user->otp_method ?? 'email',
            'destination'   => $destination,
            'expires_at'    => now()->addMinutes(5),
            'ip_address'    => $request->ip(),
        ]);

        // Kirim OTP — di production sambungkan ke driver mail / WA Gateway.
        // Untuk development: tulis ke log + simpan di session agar bisa ditampilkan ke developer.
        Log::info('OTP CBT', ['user_id' => $user->id, 'kode' => $plain, 'tujuan' => $destination]);
        if (app()->environment(['local', 'testing'])) {
            session()->flash('dev_otp', $plain);
        }

        return $otp;
    }

    protected function maskedDestination($user): string
    {
        $val = $user->email ?? $user->nomor_hp ?? '';
        if (str_contains($val, '@')) {
            [$local, $domain] = explode('@', $val);
            return substr($local, 0, 2).str_repeat('*', max(3, strlen($local) - 2)).'@'.$domain;
        }
        if (strlen($val) >= 8) {
            return substr($val, 0, 3).str_repeat('*', strlen($val) - 6).substr($val, -3);
        }
        return $val ?: '— tujuan tidak diatur —';
    }
}
