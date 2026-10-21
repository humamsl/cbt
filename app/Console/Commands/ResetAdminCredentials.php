<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\LoginFailureCap;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Respons insiden: ganti kredensial SEMUA akun panel admin di instalasi ini
 * dalam satu langkah, lalu putus semua sesi/token yang mungkin dipegang
 * penyerang.
 *
 * - Admin utama  → email admin@<sekolah>.<domain> + password baru.
 * - Admin lain   → password acak baru (atau dinonaktifkan dgn --disable-others).
 * - Semua akun di atas: remember_token diganti, sesi login & token API dihapus,
 *   lockout direset, dan wajib ganti password saat login berikutnya.
 * - Password baru ditulis SEKALI ke file CSV (storage/app/private) — hapus file
 *   itu setelah dibagikan.
 *
 * Idempoten: menjalankan ulang menghasilkan keadaan akhir yang sama (admin
 * utama dikenali dari email targetnya); bedanya hanya password acak dibuat baru.
 *
 * File ini identik di cbt, Datacenter & Perpus kecuali bagian "per aplikasi".
 */
class ResetAdminCredentials extends Command
{
    protected $signature = 'admin:reset-credentials
        {--school= : Slug sekolah untuk email admin@<school>.<domain> (default: label pertama host APP_URL)}
        {--email-domain=com : Akhiran domain email admin utama}
        {--password-template= : Pola password admin utama, mis. "{school}Garage12". Kosong = acak (disarankan)}
        {--user= : ID akun yang dijadikan admin utama (default: yang sudah ber-email target, atau ID terkecil)}
        {--disable-others : Nonaktifkan akun admin lain alih-alih hanya mengganti passwordnya}
        {--no-force-change : Jangan wajibkan ganti password saat login berikutnya}
        {--output= : Path file CSV hasil (default: storage/app/private/admin-credentials-*.csv)}
        {--dry-run : Tampilkan rencana & temuan saja, tidak mengubah apa pun}';

    protected $description = 'Ganti email/password semua akun admin, putus semua sesi & token mereka (respons insiden brute force)';

    /** Password lemah yang dicek di semua akun admin untuk laporan temuan. */
    protected const WEAK_PASSWORDS = ['password', 'admin', 'admin123', '12345678', '123456', 'password123'];

    // ===================== per aplikasi =====================

    protected const APP_LABEL = 'cbt';

    /** Guard session yang dipakai panel admin (kunci "login_<guard>_*" di payload sesi). */
    protected const SESSION_GUARDS = ['admin', 'web'];

    /**
     * Semua akun yang bisa masuk panel admin: hanya baris dengan role CBT valid —
     * tabel users bisa berisi akun aplikasi lain bila server memakai DB bersama.
     * Admin lama tanpa role_id bisa dipilih lewat --user=ID (lihat handle()).
     */
    protected function privilegedUsers(): Collection
    {
        return User::query()->panelAccounts()
            ->where(fn ($q) => $q->whereNull('role')->orWhere('role', '!=', 'service'))
            ->orderBy('id')->get();
    }

    protected function roleLabel(User $u): string
    {
        return (string) $u->getAttribute('role').' / role_id '.($u->role_id ?? '-');
    }

    protected function disableAttributes(): array
    {
        return ['account_status' => 'inactive', 'is_aktif' => false];
    }

    // ========================================================

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $forceChange = ! $this->option('no-force-change');

        $school = $this->resolveSchool();
        if (! $school) {
            $this->error('Slug sekolah tidak bisa ditebak dari APP_URL ('.config('app.url').'). Isi --school=namasekolah');
            return self::FAILURE;
        }
        $emailDomain = trim((string) $this->option('email-domain'), ". \t");
        $targetEmail = "admin@{$school}.{$emailDomain}";

        $columns = Schema::getColumnListing((new User)->getTable());
        if ($forceChange && ! in_array('must_change_password', $columns, true)) {
            $this->error('Kolom users.must_change_password belum ada. Jalankan dulu: php artisan migrate --force');
            return self::FAILURE;
        }

        $users = $this->privilegedUsers();
        // --user boleh menunjuk admin lama yang belum punya role_id; role-nya diisi saat diterapkan
        if ($this->option('user') && ! $users->firstWhere('id', (int) $this->option('user'))) {
            $picked = User::find((int) $this->option('user'));
            if ($picked) $users = $users->push($picked)->sortBy('id')->values();
        }
        if ($users->isEmpty()) {
            $this->error('Tidak ada akun admin (ber-role CBT) di database ini. Pilih akun admin dengan --user=ID.');
            return self::FAILURE;
        }

        $primary = $this->option('user')
            ? $users->firstWhere('id', (int) $this->option('user'))
            : ($users->first(fn ($u) => strcasecmp($u->email, $targetEmail) === 0) ?? $users->first());
        if (! $primary) {
            $this->error('Akun --user='.$this->option('user').' tidak ditemukan di daftar akun admin.');
            return self::FAILURE;
        }

        $clash = User::query()->where('email', $targetEmail)->where('id', '!=', $primary->id)->first();
        if ($clash) {
            $this->error("Email {$targetEmail} sudah dipakai akun lain (ID {$clash->id}). Pakai --user={$clash->id} atau ubah --school.");
            return self::FAILURE;
        }

        $template = (string) $this->option('password-template');
        $primaryPassword = $template !== '' ? str_replace('{school}', $school, $template) : $this->randomPassword();
        if (strlen($primaryPassword) < 8) {
            $this->error('Password admin utama minimal 8 karakter.');
            return self::FAILURE;
        }

        // ---- Temuan keamanan (dicek SEBELUM password diganti) ----
        $weak = [];
        foreach ($users as $u) {
            foreach (self::WEAK_PASSWORDS as $p) {
                if (Hash::check($p, (string) $u->password)) {
                    $weak[$u->id] = $p;
                    break;
                }
            }
        }

        // ---- Rencana ----
        $plan = $users->map(function (User $u) use ($primary, $targetEmail, $primaryPassword, $weak) {
            $isPrimary = $u->id === $primary->id;
            return [
                'user'     => $u,
                'primary'  => $isPrimary,
                'old_email' => $u->email,
                'email'    => $isPrimary ? $targetEmail : $u->email,
                'password' => $isPrimary ? $primaryPassword : $this->randomPassword(),
                'action'   => $isPrimary ? 'admin utama' : ($this->option('disable-others') ? 'NONAKTIFKAN' : 'password acak'),
                'weak'     => $weak[$u->id] ?? null,
            ];
        });

        $this->newLine();
        $this->info(sprintf('[%s] sekolah="%s" — %d akun admin%s', self::APP_LABEL, $school, $users->count(), $dry ? ' — DRY RUN' : ''));
        $this->table(
            ['ID', 'Role', 'Email sekarang', 'Email baru', 'Aksi', 'Password lemah?', 'Dibuat', 'Diubah'],
            $plan->map(fn ($p) => [
                $p['user']->id,
                $this->roleLabel($p['user']),
                $p['user']->email,
                $p['email'] === $p['old_email'] ? '(tetap)' : $p['email'],
                $p['action'],
                $p['weak'] ? "YA: \"{$p['weak']}\"" : '-',
                optional($p['user']->created_at)->format('Y-m-d H:i'),
                optional($p['user']->updated_at)->format('Y-m-d H:i'),
            ])->all()
        );
        $this->reportFindings($weak, $targetEmail);

        $ids = $users->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($dry) {
            $this->line(sprintf('Akan dihapus: %d sesi login, %d token API.', $this->purgeSessions($ids, true), $this->purgeTokens($ids, true)));
            $this->warn('DRY RUN — tidak ada yang diubah. Jalankan tanpa --dry-run untuk menerapkan.');
            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->confirm('Terapkan perubahan di atas?', true)) {
            $this->warn('Dibatalkan.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($plan, $columns, $forceChange) {
            foreach ($plan as $p) {
                $attrs = [
                    'email'                => $p['email'],
                    'password'             => Hash::make($p['password']),
                    'remember_token'       => Str::random(60),
                    'failed_login_count'   => 0,
                    'locked_until'         => null,
                    'must_change_password' => $forceChange,
                ];
                if (! $p['primary'] && $this->option('disable-others')) {
                    $attrs += $this->disableAttributes();
                }
                // Login admin mensyaratkan role valid (User::scopePanelAccounts)
                if ($p['primary'] && ! $p['user']->isPanelAccount()) {
                    $attrs['role_id'] = \App\Models\Role::where('name', 'super-admin')->value('id');
                }
                $p['user']->forceFill(array_intersect_key($attrs, array_flip($columns)))->save();
            }
        });

        foreach ($plan as $p) {
            LoginFailureCap::clear($p['old_email']);
            LoginFailureCap::clear($p['email']);
        }
        $sessions = $this->purgeSessions($ids, false);
        $tokens = $this->purgeTokens($ids, false);
        $csv = $this->writeCsv($plan, $school, $forceChange);

        Log::warning('admin:reset-credentials dijalankan', [
            'app'      => self::APP_LABEL,
            'school'   => $school,
            'primary'  => ['id' => $primary->id, 'email' => $targetEmail],
            'accounts' => $plan->map(fn ($p) => ['id' => $p['user']->id, 'email' => $p['email'], 'action' => $p['action']])->values()->all(),
            'sessions_deleted' => $sessions,
            'tokens_deleted'   => $tokens,
            'os_user'  => get_current_user(),
        ]);

        $this->newLine();
        $this->table(['ID', 'Email login', 'Password baru'], $plan->map(fn ($p) => [$p['user']->id, $p['email'], $p['password']])->all());
        $this->info("Selesai: {$sessions} sesi & {$tokens} token API dihapus. Semua akun admin harus login ulang.");
        $this->info("Kredensial juga ditulis ke: {$csv}");
        $this->warn('Bagikan lewat jalur aman, lalu HAPUS file itu: rm '.escapeshellarg($csv));

        return self::SUCCESS;
    }

    protected function resolveSchool(): ?string
    {
        $raw = (string) ($this->option('school') ?: '');
        if ($raw === '') {
            $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
            if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) || ! str_contains($host, '.')) {
                return null;
            }
            $raw = explode('.', $host)[0];
        }
        $slug = preg_replace('/[^a-z0-9-]/', '', Str::lower($raw));

        return $slug !== '' ? $slug : null;
    }

    protected function randomPassword(): string
    {
        // Tanpa simbol & tanpa karakter mirip (0/O, 1/l/I) supaya mudah didiktekan.
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 16; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    protected function reportFindings(array $weak, string $targetEmail): void
    {
        $notes = [];
        if ($weak) {
            $notes[] = count($weak).' akun admin memakai password lemah/default (lihat kolom "Password lemah?") — kemungkinan besar ini jalur masuk penyerang.';
        }
        if (config('app.debug')) {
            $notes[] = 'APP_DEBUG=true — set APP_DEBUG=false di .env (halaman error membocorkan konfigurasi & query).';
        }
        if (! app()->environment('production')) {
            $notes[] = 'APP_ENV='.app()->environment().' — set APP_ENV=production di .env.';
        }
        if (! Str::endsWith($targetEmail, ['.sch.id', '.cyberedugarage.id', '.educore.web.id'])) {
            $notes[] = "Email {$targetEmail} memakai domain yang mungkin dimiliki orang lain. Kode OTP/reset password yang dikirim ke email ini bisa diterima pemilik domain tsb.";
        }
        foreach ($notes as $n) {
            $this->warn('[!] '.$n);
        }
    }

    /**
     * Hapus sesi milik akun-akun ini. Login via guard non-default (mis. 'admin')
     * TIDAK mengisi kolom sessions.user_id, jadi payload tiap sesi dibaca untuk
     * mencari kunci "login_<guard>_<hash>" yang berisi ID akun tsb.
     */
    protected function purgeSessions(array $ids, bool $countOnly): int
    {
        $driver = config('session.driver');
        $matches = function (?string $raw) use ($ids): bool {
            $data = $this->decodeSession($raw);
            foreach ($data as $key => $value) {
                foreach (self::SESSION_GUARDS as $g) {
                    if (str_starts_with((string) $key, "login_{$g}_") && in_array((int) $value, $ids, true)) {
                        return true;
                    }
                }
            }
            return false;
        };

        if ($driver === 'database') {
            $conn = DB::connection(config('session.connection'));
            $table = config('session.table', 'sessions');
            $hit = [];
            $conn->table($table)->select(['id', 'user_id', 'payload'])->orderBy('id')
                ->chunk(500, function ($rows) use (&$hit, $ids, $matches) {
                    foreach ($rows as $row) {
                        if (($row->user_id !== null && in_array((int) $row->user_id, $ids, true))
                            || $matches(base64_decode((string) $row->payload, true) ?: null)) {
                            $hit[] = $row->id;
                        }
                    }
                });
            if (! $countOnly) {
                foreach (array_chunk($hit, 500) as $chunk) {
                    $conn->table($table)->whereIn('id', $chunk)->delete();
                }
            }

            return count($hit);
        }

        if ($driver === 'file') {
            $n = 0;
            foreach (File::files(config('session.files')) as $file) {
                if ($matches(@file_get_contents($file->getPathname()) ?: null)) {
                    $n++;
                    if (! $countOnly) {
                        @unlink($file->getPathname());
                    }
                }
            }

            return $n;
        }

        $this->warn("Driver sesi '{$driver}' tidak bisa dipindai — kosongkan semua sesi manual (semua user akan logout).");

        return 0;
    }

    protected function decodeSession(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (config('session.encrypt')) {
            try {
                $raw = Crypt::decrypt($raw);
            } catch (\Throwable) {
                return [];
            }
        }
        $data = config('session.serialization') === 'json'
            ? json_decode((string) $raw, true)
            : @unserialize((string) $raw, ['allowed_classes' => false]);

        return is_array($data) ? $data : [];
    }

    protected function purgeTokens(array $ids, bool $countOnly): int
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return 0;
        }
        $q = DB::table('personal_access_tokens')
            ->whereIn('tokenable_type', array_unique([User::class, (new User)->getMorphClass()]))
            ->whereIn('tokenable_id', $ids);

        return $countOnly ? $q->count() : $q->delete();
    }

    protected function writeCsv(Collection $plan, string $school, bool $forceChange): string
    {
        $path = $this->option('output')
            ?: storage_path('app/private/admin-credentials-'.self::APP_LABEL.'-'.$school.'-'.now()->format('Ymd-His').'.csv');
        File::ensureDirectoryExists(dirname($path), 0700);

        $fh = fopen($path, 'w');
        fputcsv($fh, ['aplikasi', 'sekolah', 'id', 'email_login', 'password', 'aksi', 'wajib_ganti_password']);
        foreach ($plan as $p) {
            fputcsv($fh, [self::APP_LABEL, $school, $p['user']->id, $p['email'], $p['password'], $p['action'], $forceChange ? 'ya' : 'tidak']);
        }
        fclose($fh);
        @chmod($path, 0600);

        return $path;
    }
}
