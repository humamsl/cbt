<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * RBAC sederhana: ambil page/action dari NAMA ROUTE (mis. hasil.detail → hasil/detail),
 * lalu cek $user->canAccess(). Fallback ke segmen URL kalau route tanpa nama.
 *
 * Halaman whitelist (dashboard, profil, otp, account, logout) selalu lolos.
 */
class Rbac
{
    protected array $whitelist = [
        'dashboard', 'profil', 'profile', 'otp', 'logout', 'account',
        'login', 'up',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) return $next($request);

        // 1) Coba ambil dari NAMA ROUTE (paling akurat: cocok dengan permission yang di-seed)
        $routeName = $request->route()?->getName();
        if ($routeName) {
            $parts = explode('.', $routeName);
            $page = strtolower($parts[0] ?? 'dashboard');
            $action = strtolower($parts[1] ?? 'index');
        } else {
            // 2) Fallback ke segmen URL (untuk route tanpa nama)
            $page = strtolower($request->segment(1, 'dashboard'));
            $action = strtolower($request->segment(2, 'index'));
            // Jika action ternyata angka (id) → anggap 'index' / detail
            if (ctype_digit($action) || $action === '') {
                $action = 'index';
            }
        }

        if (in_array($page, $this->whitelist, true)) {
            return $next($request);
        }

        if (! method_exists($user, 'canAccess')) {
            return $next($request);
        }

        if (! $user->canAccess("$page/$action")) {
            abort(403, 'Anda tidak memiliki izin akses ke halaman ini.');
        }

        return $next($request);
    }
}
