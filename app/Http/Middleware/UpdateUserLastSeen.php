<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            // forceFill()->save() otomatis pakai connection milik model ($user
            // bisa Guru/Siswa yang terhubung ke DB Data Center, atau User/admin
            // di DB lokal — DB::table(...) polos akan salah sasaran utk yang pertama.
            $user->forceFill(['last_seen_at' => now()])->save();
        } catch (\Throwable $e) {
            // ignore — kolom mungkin belum ter-migrate
        }

        return $next($request);
    }
}
