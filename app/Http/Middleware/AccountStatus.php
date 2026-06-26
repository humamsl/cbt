<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cek field `account_status` user. Jika bukan 'active' arahkan ke halaman peringatan.
 * Nilai yang dikenal: active, suspended, locked, inactive
 */
class AccountStatus
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $status = strtolower((string) ($user->account_status ?? 'active'));

        // Cek penguncian sementara karena gagal login
        if (! empty($user->locked_until) && $user->locked_until > now()) {
            $this->logoutAll($request);
            return redirect()->route('account.locked')
                ->with('locked_until', $user->locked_until);
        }

        if ($status !== 'active') {
            $route = match ($status) {
                'suspended' => 'account.suspended',
                'locked'    => 'account.locked',
                'inactive'  => 'account.inactive',
                default     => 'account.suspended',
            };
            $this->logoutAll($request);
            return redirect()->route($route);
        }

        return $next($request);
    }

    protected function logoutAll(Request $request): void
    {
        foreach (['admin', 'guru', 'siswa', 'web'] as $g) {
            if (Auth::guard($g)->check()) Auth::guard($g)->logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
