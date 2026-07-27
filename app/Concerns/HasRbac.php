<?php

namespace App\Concerns;

use App\Models\Role;

/**
 * Trait kemampuan RBAC yang dipasang ke User/Guru/Siswa.
 * - Admin (User): role_id terhubung ke roles table → cek permission.
 * - Guru: full access ke modul cbt/datacenter (sesuai konvensi sekolah).
 * - Siswa: hanya halaman ujian.
 */
trait HasRbac
{
    protected array $cachedPermissions = [];
    protected array $cachedRoleNames = [];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function getPermissions(): array
    {
        if (! empty($this->cachedPermissions)) return $this->cachedPermissions;

        // Admin dengan role custom (User model)
        if ($this->relationLoaded('role') === false && method_exists($this, 'role')) {
            $role = $this->role()->with('permissions')->first();
            if ($role) {
                $this->cachedRoleNames = [$role->name];

                // Super Admin & Admin selalu akses penuh ke SEMUA fitur, terlepas
                // dari apa yang tercatat di tabel permission — supaya fitur baru
                // yang lupa didaftarkan ke seeder RBAC tidak ikut memblokir peran
                // tertinggi ini (lihat riwayat migrasi grant-permission periodikal
                // & pengaturan sebelumnya — pola yang sama, ditambal di akarnya).
                if (in_array($role->name, ['super-admin', 'admin'], true)) {
                    return $this->cachedPermissions = ['*'];
                }

                $this->cachedPermissions = $role->permissions->pluck('permission')->toArray();
                return $this->cachedPermissions;
            }
        }

        // Default per tipe user
        $type = $this->user_type ?? 'guest';
        return $this->cachedPermissions = match ($type) {
            'admin' => ['*'],                 // wildcard, akses semua
            'guru'  => $this->guruDefaultPermissions(),
            'siswa' => $this->siswaDefaultPermissions(),
            default => [],
        };
    }

    public function canAccess(string $path): bool
    {
        $perms = $this->getPermissions();
        if (in_array('*', $perms, true)) return true;

        $path = strtolower(trim($path, '/'));
        $segments = explode('/', $path);
        $page = $segments[0] ?? 'dashboard';
        $action = $segments[1] ?? 'index';
        $pagePath = "$page/$action";

        if (in_array($pagePath, $perms, true)) return true;
        if (in_array("$page/*", $perms, true)) return true;

        return false;
    }

    public function hasRole(string|array $roles): bool
    {
        $arr = (array) $roles;
        $userRoles = $this->cachedRoleNames ?: [$this->user_type ?? ''];
        return (bool) array_intersect(array_map('strtolower', $arr), array_map('strtolower', $userRoles));
    }

    protected function guruDefaultPermissions(): array
    {
        // guru: HANYA akses modul CBT (tidak boleh sentuh Data Center)
        return [
            'dashboard/index', 'profil/index', 'profil/password',
            'topik/*', 'bank-soal/*', 'tes/*', 'token-sesi/*',
            'hasil/*',  // index, detail, statistik, analisis, semua export
            // monitoring: guru dibatasi di MonitoringController — hanya ujian
            // buatannya sendiri + kelas yang di-grant admin (monitoring_akses).
            // Halaman Setting Akses tetap admin-only lewat middleware 'admin'.
            'monitoring/*',
            'otp/*',
        ];
    }

    protected function siswaDefaultPermissions(): array
    {
        return [
            'dashboard/index', 'profil/index', 'profil/password',
            'ujian/*', 'riwayat/index',
            'otp/*',
        ];
    }
}
