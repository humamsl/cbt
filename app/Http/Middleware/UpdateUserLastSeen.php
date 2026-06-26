<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateUserLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) return $next($request);

        $key = sprintf('lastseen:%s:%d', $user::class, $user->id);

        // throttle: update maksimal 1x / menit
        if (Cache::has($key)) {
            return $next($request);
        }
        Cache::put($key, true, now()->addMinute());

        try {
            DB::table($user->getTable())
                ->where($user->getKeyName(), $user->getKey())
                ->update(['last_seen_at' => now()]);
        } catch (\Throwable $e) {
            // ignore — kolom mungkin belum ter-migrate
        }

        return $next($request);
    }
}
