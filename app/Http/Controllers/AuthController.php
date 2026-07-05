<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\Jurusan;
use App\Models\LoginAttempt;
use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\DatacenterClient;
use App\Services\DatacenterSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public const MAX_ATTEMPTS = 5;
    public const LOCK_MINUTES = 15;

    public function showLogin(Request $request, ?string $module = null)
    {
        // Pintu login modul (Data Center / CBT): kalau user sedang login di
        // modul LAIN, wajib login ulang untuk pindah -> logout paksa dulu.
        // Kalau modul yang dituju SAMA dengan yang sedang aktif, tidak perlu
        // login ulang, langsung saja ke dashboard.
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
        // Jaga-jaga: kalau form ke-submit saat sesi lama (modul lain) masih
        // menempel (mis. tab lama), paksa logout dulu sebelum memproses
        // percobaan login yang baru.
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

        // ---- IP-based rate limit (anti brute force) ----
        $rateKey = 'login:'.$request->ip().':'.$data['role'];
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $seconds = RateLimiter::availableIn($rateKey);
            throw ValidationException::withMessages([
                'username' => "Terlalu banyak percobaan dari IP ini. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $result = $guard === 'admin'
            ? $this->attemptAdmin($request, $data, $remember, $rateKey)
            : $this->attemptRemote($request, $guard, $data, $remember, $rateKey);

        // attemptAdmin/attemptRemote mengembalikan RedirectResponse kalau akun
        // ditemukan tapi statusnya bukan 'active' (suspended/locked/inactive).
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

        // Simpan konteks modul yang dipakai untuk login (menentukan tampilan
        // sidebar setelah masuk). Login umum (module null) tidak mengubah
        // konteks lama — perilaku lama (tampilkan semua menu) tetap berjalan.
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

            DB::table($user->getTable())
                ->where($user->getKeyName(), $user->getKey())
                ->update([
                    'current_session_id' => session()->getId(),
                    'current_device'     => substr((string) $request->userAgent(), 0, 100),
                ]);

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
        $existing = User::where('email', $data['username'])->first();
        if ($existing && ! empty($existing->locked_until) && $existing->locked_until > now()) {
            $this->logAttempt($request, $data, false);
            $minutes = now()->diffInMinutes($existing->locked_until);
            throw ValidationException::withMessages([
                'username' => "Akun dikunci sementara. Coba lagi dalam {$minutes} menit.",
            ]);
        }

        $ok = Auth::guard('admin')->attempt(['email' => $data['username'], 'password' => $data['password']], $remember);

        $this->logAttempt($request, $data, $ok);
        RateLimiter::hit($rateKey, 60 * 10);

        if (! $ok) {
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

    /**
     * Login guru/siswa — password TIDAK pernah dicek lokal. Diverifikasi via
     * API Data Center (satu-satunya pemilik password), lalu baris cache lokal
     * (guru/siswa) di-upsert dari respons API supaya siswa/guru yang baru
     * ditambahkan di Data Center langsung bisa login di CBT tanpa sinkronisasi
     * manual (lihat DatacenterClient & upsertSiswaFromDatacenter/upsertGuruFromDatacenter).
     */
    protected function attemptRemote(Request $request, string $guard, array $data, bool $remember, string $rateKey): bool|\Illuminate\Http\RedirectResponse
    {
        $client = app(DatacenterClient::class);
        $response = $guard === 'guru'
            ? $client->verifyGuru($data['username'], $data['password'])
            : $client->verifySiswa($data['username'], $data['password']);

        RateLimiter::hit($rateKey, 60 * 10);
        $this->logAttempt($request, $data, $response->successful());

        if ($response->status() === 423) {
            throw ValidationException::withMessages([
                'username' => $response->json('message', 'Akun dikunci sementara.'),
            ]);
        }

        if ($response->status() === 403) {
            $status = strtolower((string) $response->json('account_status', 'inactive'));
            return redirect()->route('account.'.($status === 'active' ? 'inactive' : $status));
        }

        if (! $response->successful()) {
            return false;
        }

        $payload = (array) $response->json('data', []);

        $user = DB::transaction(fn () => $guard === 'guru'
            ? $this->upsertGuruFromDatacenter($payload)
            : $this->upsertSiswaFromDatacenter($payload));

        Auth::guard($guard)->login($user, $remember);

        return true;
    }

    // Upsert siswa/guru dari Data Center dipindah ke App\Services\DatacenterSync
    // supaya dipakai bersama oleh login (lazy per-orang) & `datacenter:sync` (bulk).
    protected function upsertSiswaFromDatacenter(array $data): Siswa
    {
        return app(DatacenterSync::class)->upsertSiswa($data);
    }

    protected function upsertGuruFromDatacenter(array $data): Guru
    {
        return app(DatacenterSync::class)->upsertGuru($data);
    }

    public function logout(Request $request)
    {
        $this->forceLogout($request);
        return redirect()->away(config('services.landing.app_url'));
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

    protected function logAttempt(Request $r, array $data, bool $success): void
    {
        $ua = (string) $r->userAgent();
        $parsed = $this->parseUserAgent($ua);

        // Hitung attempt ke-berapa untuk username ini hari ini
        $attemptNo = LoginAttempt::where('username', $data['username'])
            ->whereDate('created_at', today())->count() + 1;

        LoginAttempt::create([
            'username'   => $data['username'],
            'guard'      => $data['role'],
            'success'    => $success,
            'ip_address' => $r->ip(),
            'user_agent' => substr($ua, 0, 500),
            'device_type'=> $parsed['device'],
            'browser'    => $parsed['browser'],
            'os'         => $parsed['os'],
            'attempt_no' => $attemptNo,
        ]);
    }

    /** Parser sederhana user agent → device/browser/OS */
    protected function parseUserAgent(string $ua): array
    {
        $device = 'desktop';
        if (preg_match('/iPad|Tablet/i', $ua)) $device = 'tablet';
        elseif (preg_match('/Mobi|Android|iPhone|iPod|BlackBerry|Opera Mini/i', $ua)) $device = 'mobile';

        $browser = 'Other';
        if (preg_match('/Edg\//i', $ua)) $browser = 'Edge';
        elseif (preg_match('/Chrome\//i', $ua)) $browser = 'Chrome';
        elseif (preg_match('/Firefox\//i', $ua)) $browser = 'Firefox';
        elseif (preg_match('/Safari\//i', $ua) && ! preg_match('/Chrome|Edg/i', $ua)) $browser = 'Safari';
        elseif (preg_match('/OPR\/|Opera/i', $ua)) $browser = 'Opera';

        $os = 'Other';
        if (preg_match('/Windows NT 10/i', $ua)) $os = 'Windows 10/11';
        elseif (preg_match('/Windows NT/i', $ua)) $os = 'Windows';
        elseif (preg_match('/Mac OS X/i', $ua)) $os = 'macOS';
        elseif (preg_match('/Android/i', $ua)) $os = 'Android';
        elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $os = 'iOS';
        elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';

        return compact('device', 'browser', 'os');
    }
}
