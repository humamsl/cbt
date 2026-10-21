<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin yang kredensialnya baru direset (`admin:reset-credentials`) wajib
 * mengganti password dulu — semua halaman lain diarahkan ke Profil sampai
 * password diganti (ProfilController::updatePassword menghapus tandanya).
 */
class ForcePasswordChange
{
    protected const ALLOWED_ROUTES = ['profil.*', 'logout', 'otp.*', 'csrf.refresh'];

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('admin')->user();

        if ($user && $user->must_change_password && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            $message = 'Demi keamanan, ganti password Anda terlebih dahulu sebelum melanjutkan.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 423);
            }

            return redirect()->route('profil.index')->with('error', $message);
        }

        return $next($request);
    }
}
