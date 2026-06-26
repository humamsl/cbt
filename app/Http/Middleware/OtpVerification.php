<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Jika user mengaktifkan OTP (otp_enabled = true) dan belum lulus verifikasi pada
 * session ini, paksa redirect ke halaman verifikasi.
 *
 * Bypass: halaman OTP itu sendiri dan logout.
 */
class OtpVerification
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) return $next($request);

        if (! ($user->otp_enabled ?? false)) {
            return $next($request);
        }

        // sudah verifikasi → lewat
        if (session('otp_verified_at')) {
            return $next($request);
        }

        // jangan loop di endpoint OTP sendiri & logout
        if ($request->routeIs('otp.*') || $request->routeIs('logout')) {
            return $next($request);
        }

        return redirect()->route('otp.show');
    }
}
