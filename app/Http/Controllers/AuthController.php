<?php

namespace App\Http\Controllers;

use App\Concerns\LogsLoginAttempts;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use App\Support\LoginFailureCap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use LogsLoginAttempts;

    public const MAX_ATTEMPTS = 5;
    public const LOCK_MINUTES = 15;
    /** Login admin gagal per IP per jam (semua username digabung). */
    public const ADMIN_IP_MAX_FAILURES = 30;

    public function showLogin(Request $request, ?string $module = null)
    {

        if ($module && $this->currentGuardName()) {
            if (session('active_module') === $module) {
                return redirect()->route('dashboard');
            }
            $this->forceLogout($request);
        }

        // Pastikan halaman login TIDAK di-cache oleh browser (penyebab 419 di mobile)
        // dan kunci CSRF selalu segar tiap akses.
        if (! $request->session()->has('_token')) {
            $request->session()->regenerateToken();
        }

        return response()
            ->view('auth.login', [
                'module'       => $module,
                'allowedRoles' => $this->allowedRolesFor($module),
                'postRoute'    => $this->postRouteFor($module),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function login(Request $request, ?string $module = null)
    {

        if ($module && $this->currentGuardName() && session('active_module') !== $module) {
            $this->forceLogout($request);
        }

        $allowedRoles = $this->allowedRolesFor($module);

        $data = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:100',
            'role'     => 'required|in:'.implode(',', $allowedRoles),
        ]);

        $remember = (bool) $request->boolean('remember');
        $guard = $data['role'];

        $rateKey = 'login:'.$request->ip().':'.$data['role'].':'.$data['username'];
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $seconds = RateLimiter::availableIn($rateKey);
            throw ValidationException::withMessages([
                'username' => "Terlalu banyak percobaan dari IP ini. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $result = $guard === 'admin'
            ? $this->attemptAdmin($request, $data, $remember, $rateKey)
            : $this->attemptRemote($request, $guard, $data, $remember, $rateKey);

        if ($result instanceof \Illuminate\Http\RedirectResponse) {
            return $result;
        }

        if (! $result) {
            throw ValidationException::withMessages([
                'username' => 'Kombinasi username dan password salah.',
            ]);
        }

        // ---- Sukses ----
        $user = Auth::guard($guard)->user();

        RateLimiter::clear($rateKey);
        $request->session()->regenerate();

        if ($module) {
            $request->session()->put('active_module', $module);
        } else {
            $request->session()->forget('active_module');
        }

        // ===== SINGLE SIGN-ON (HANYA SISWA) =====
        // Admin & guru tidak terikat SSO — mereka boleh login di banyak perangkat.
        if ($guard === 'siswa') {
            $hadOtherSession = ! empty($user->current_session_id)
                            && $user->current_session_id !== session()->getId();

            $user->forceFill([
                'current_session_id' => session()->getId(),
                'current_device'     => substr((string) $request->userAgent(), 0, 100),
            ])->save();

            if ($hadOtherSession) {
                session()->flash('error', '⚠ Akun Anda sebelumnya aktif di perangkat lain. Perangkat tersebut otomatis di-logout.');
            }
        }

        // Jika OTP aktif, hapus tanda verifikasi sebelumnya supaya wajib verify
        if (! empty($user->otp_enabled)) {
            session()->forget('otp_verified_at');
            return redirect()->route('otp.show');
        }

        return redirect()->intended(route('dashboard'));
    }

    /** Login admin — sepenuhnya lokal (User tetap dikelola langsung di CBT). */
    protected function attemptAdmin(Request $request, array $data, bool $remember, string $rateKey): bool|\Illuminate\Http\RedirectResponse
    {
        // Batas tambahan khusus admin (siswa sengaja tidak — banyak siswa berbagi
        // satu IP sekolah): per akun per 24 jam dari IP mana pun, dan per IP untuk
        // semua username admin (menahan penebakan banyak email dari satu IP).
        $ipKey = 'login-admin-ip:'.$request->ip();
        $blocked = LoginFailureCap::blockedFor($data['username'])
            ?? (RateLimiter::tooManyAttempts($ipKey, self::ADMIN_IP_MAX_FAILURES) ? RateLimiter::availableIn($ipKey) : null);
        if ($blocked !== null) {
            $this->logAttempt($request, $data['username'], $data['role'], false);
            throw ValidationException::withMessages(['username' => LoginFailureCap::message($blocked)]);
        }

        $existing = User::where('email', $data['username'])->first();
        if ($existing && ! empty($existing->locked_until) && $existing->locked_until > now()) {
            $this->logAttempt($request, $data['username'], $data['role'], false);
            $minutes = max(1, (int) ceil(now()->diffInMinutes($existing->locked_until)));
            throw ValidationException::withMessages([
                'username' => "Akun dikunci sementara. Coba lagi dalam {$minutes} menit.",
            ]);
        }

        // Hanya akun panel CBT (punya role valid) — lihat User::scopePanelAccounts()
        $ok = Auth::guard('admin')->attempt([
            'email'    => $data['username'],
            'password' => $data['password'],
            fn ($q) => $q->panelAccounts(),
        ], $remember);

        $this->logAttempt($request, $data['username'], $data['role'], $ok);
        RateLimiter::hit($rateKey, 60 * 10);

        if (! $ok) {
            LoginFailureCap::hit($data['username']);
            RateLimiter::hit($ipKey, 3600);
            if ($existing) {
                $count = (int) ($existing->failed_login_count ?? 0) + 1;
                $updates = ['failed_login_count' => $count];
                if ($count >= self::MAX_ATTEMPTS) {
                    $updates['locked_until'] = now()->addMinutes(self::LOCK_MINUTES);
                    $updates['failed_login_count'] = 0;
                }
                DB::table($existing->getTable())->where('id', $existing->id)->update($updates);
            }
            return false;
        }

        LoginFailureCap::clear($data['username']);

        $user = Auth::guard('admin')->user();
        $status = strtolower((string) ($user->account_status ?? 'active'));
        if ($status !== 'active') {
            Auth::guard('admin')->logout();
            return redirect()->route('account.'.$status);
        }

        DB::table('users')->where('id', $user->id)
            ->update(['failed_login_count' => 0, 'locked_until' => null, 'last_seen_at' => now()]);

        return true;
    }

    protected function attemptRemote(Request $request, string $guard, array $data, bool $remember, string $rateKey): bool|\Illuminate\Http\RedirectResponse
    {
        $model = $guard === 'guru' ? Guru::class : Siswa::class;
        $usernameField = $guard === 'guru' ? 'nip' : 'nisn';

        $existing = $model::where($usernameField, $data['username'])->first();
        if ($existing && ! empty($existing->locked_until) && $existing->locked_until > now()) {
            $this->logAttempt($request, $data['username'], $data['role'], false);
            $minutes = max(1, (int) ceil(now()->diffInMinutes($existing->locked_until)));
            throw ValidationException::withMessages([
                'username' => "Akun dikunci sementara. Coba lagi dalam {$minutes} menit.",
            ]);
        }

        $ok = Auth::guard($guard)->attempt([$usernameField => $data['username'], 'password' => $data['password']], $remember);

        $this->logAttempt($request, $data['username'], $data['role'], $ok);
        RateLimiter::hit($rateKey, 60 * 10);

        if (! $ok) {
            if ($existing) {
                $count = (int) ($existing->failed_login_count ?? 0) + 1;
                $updates = ['failed_login_count' => $count];
                if ($count >= self::MAX_ATTEMPTS) {
                    $updates['locked_until'] = now()->addMinutes(self::LOCK_MINUTES);
                    $updates['failed_login_count'] = 0;
                }
                $existing->forceFill($updates)->save();
            }
            return false;
        }

        $user = Auth::guard($guard)->user();
        $status = strtolower((string) ($user->account_status ?? 'active'));
        if ($status !== 'active') {
            Auth::guard($guard)->logout();
            return redirect()->route('account.'.($status === 'active' ? 'inactive' : $status));
        }

        $user->forceFill(['failed_login_count' => 0, 'locked_until' => null, 'last_seen_at' => now()])->save();

        return true;
    }

    public function logout(Request $request)
    {
        $this->forceLogout($request);
        return redirect('/');
    }

    /** Guard yang sedang login saat ini (admin/guru/siswa), atau null kalau belum login. */
    protected function currentGuardName(): ?string
    {
        foreach (['admin', 'guru', 'siswa'] as $g) {
            if (Auth::guard($g)->check()) return $g;
        }
        return null;
    }

    /** Logout paksa dari semua guard + bersihkan sesi (dipakai saat logout biasa maupun saat pindah modul). */
    protected function forceLogout(Request $request): void
    {
        foreach (['admin', 'guru', 'siswa'] as $g) {
            if (Auth::guard($g)->check()) Auth::guard($g)->logout();
        }
        session()->forget('otp_verified_at');
        session()->forget('active_module');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /** Daftar role yang boleh login pada modul tertentu. */
    protected function allowedRolesFor(?string $module): array
    {
        return match ($module) {
            'cbt'        => ['admin', 'guru', 'siswa'],
            default      => ['admin', 'guru', 'siswa'], // login umum (legacy)
        };
    }

    /** Nama route tempat form login modul tertentu di-submit. */
    protected function postRouteFor(?string $module): string
    {
        return match ($module) {
            'cbt'        => 'cbt.login.post',
            default      => 'login.post',
        };
    }

}
