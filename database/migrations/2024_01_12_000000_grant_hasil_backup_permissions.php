<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration:
 *  - Tambah permission baru: hasil/*, backup/*, hasil/statistik, hasil/analisis, hasil/export
 *  - Sync ke role 'admin' dan 'super-admin' yang sudah ada di DB
 *  - Hapus permission lama 'hasil/index' & 'hasil/detail' (digantikan wildcard hasil/*)
 *
 * Tidak merubah skema — hanya isi data tabel permissions & role_permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->tableExists('roles') || ! $this->tableExists('permissions') || ! $this->tableExists('role_permissions')) {
            return;
        }

        // 1) Pastikan permission baru ada
        $newPermissions = [
            ['permission' => 'hasil/*',    'label' => 'Hasil, Statistik, Analisis & Export', 'group' => 'cbt'],
            ['permission' => 'backup/*',   'label' => 'Backup & Restore',                    'group' => 'admin'],
        ];

        foreach ($newPermissions as $p) {
            $exists = DB::table('permissions')->where('permission', $p['permission'])->first();
            if (! $exists) {
                DB::table('permissions')->insert(array_merge($p, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        // 2) Ambil id semua permission baru
        $newIds = DB::table('permissions')
            ->whereIn('permission', ['hasil/*', 'backup/*'])
            ->pluck('id')->toArray();

        // 3) Grant ke role admin & super-admin
        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'super-admin'])
            ->pluck('id')->toArray();

        foreach ($roleIds as $roleId) {
            foreach ($newIds as $permId) {
                $already = DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permId)
                    ->exists();
                if (! $already) {
                    DB::table('role_permissions')->insert([
                        'role_id'       => $roleId,
                        'permission_id' => $permId,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        }

        // 4) (Opsional) Hapus permission lama hasil/index & hasil/detail karena sudah tertutup wildcard
        $oldIds = DB::table('permissions')
            ->whereIn('permission', ['hasil/index', 'hasil/detail'])
            ->pluck('id')->toArray();

        if (! empty($oldIds)) {
            DB::table('role_permissions')->whereIn('permission_id', $oldIds)->delete();
            DB::table('permissions')->whereIn('id', $oldIds)->delete();
        }
    }

    public function down(): void
    {
        if (! $this->tableExists('permissions')) return;

        // Hapus mapping & permission baru
        $newIds = DB::table('permissions')
            ->whereIn('permission', ['hasil/*', 'backup/*'])
            ->pluck('id')->toArray();

        if (! empty($newIds)) {
            DB::table('role_permissions')->whereIn('permission_id', $newIds)->delete();
            DB::table('permissions')->whereIn('id', $newIds)->delete();
        }

        // Tidak restore yang lama (sudah dihapus) — biarkan saja
    }

    protected function tableExists(string $table): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
};
