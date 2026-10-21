<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanda "wajib ganti password saat login berikutnya". Diset oleh
 * `php artisan admin:reset-credentials`, dihapus otomatis begitu user
 * mengganti password lewat halaman profil (lihat ForcePasswordChange).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $t) {
                $t->boolean('must_change_password')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('must_change_password');
            });
        }
    }
};
