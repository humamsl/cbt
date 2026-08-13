<?php

namespace App\Http\Middleware;

use App\Support\LicenseManager;
use Closure;
use Illuminate\Http\Request;

class CheckLicense
{
    public function handle(Request $request, Closure $next)
    {
        $result = LicenseManager::check($request->getHost());

        if (! $result['ok']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Lisensi aplikasi tidak valid: '.$result['reason'],
                ], 403);
            }

            return response()->view('errors.license', [
                'reason'      => $result['reason'],
                'host'        => LicenseManager::currentHost($request),
                'fingerprint' => LicenseManager::fingerprint(),
            ], 403);
        }

        return $next($request);
    }
}
