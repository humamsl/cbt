<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Khusus admin (user_type === 'admin').
 * Digunakan untuk modul ultra-sensitif (mis. backup, reset data, app setting, kelola admin).
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ($user->user_type ?? '') !== 'admin') {
            abort(403, 'Hanya administrator yang dapat mengakses halaman ini.');
        }
        return $next($request);
    }
}
