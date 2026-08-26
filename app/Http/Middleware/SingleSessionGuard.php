<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * SINGLE SIGN ON GUARD.
 * - Saat user login dari device baru, session lama dianggap stale.
 * - Saat user (yang lama) request berikutnya → terlempar logout + flash alert.
 *
 * Bandingkan session_id sekarang dengan `current_session_id` yang ter-record di
 * baris user pada DB. Jika tidak sama → forced logout.
 */
class SingleSessionGuard
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) return $next($request);

        // SSO HANYA berlaku untuk akun SISWA. Admin & guru bypass.
        if (! $user instanceof \App\Models\Siswa) {
            return $next($request);
        }

        // Skip cek di endpoint OTP / logout / halaman blokir agar tidak loop
        if ($request->routeIs('logout', 'otp.*', 'account.*')) {
            return $next($request);
        }

        $currentSid = session()->getId();
        $storedSid  = $user->current_session_id;

        // Jika belum pernah di-set → set sekarang (sesi pertama)
        if (empty($storedSid)) {
            $user->forceFill([
                'current_session_id' => $currentSid,
                'current_device' => substr((string) $request->userAgent(), 0, 100),
            ])->save();
            return $next($request);
        }

        // Jika ada session lain tercatat & berbeda → user ini di-kick
        if ($currentSid !== $storedSid) {
            foreach (['admin', 'guru', 'siswa'] as $g) {
                if (Auth::guard($g)->check()) Auth::guard($g)->logout();
            }
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $pesan = '⚠ Akun Anda sedang aktif di perangkat lain. Anda di-logout dari perangkat ini.';

            // Halaman ujian tidak pernah pindah halaman selama siswa mengerjakan
            // -- yang jalan cuma fetch (simpan jawaban, lapor pelanggaran, ping).
            // Kalau kita balas redirect HTML, fetch akan mengikutinya diam-diam
            // dan siswa tetap duduk di halaman ujian yang sudah mati tanpa tahu
            // apa-apa. Jadi untuk request JSON kita balas 409 + penanda eksplisit
            // yang dibaca cbtExam.handleKickedResponse() di show.blade.php.
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'session_conflict' => true,
                    'message'          => $pesan,
                    'redirect'         => route('login'),
                ], 409);
            }

            return redirect()->route('login')->with('error', $pesan);
        }

        return $next($request);
    }
}
