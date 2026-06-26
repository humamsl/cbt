<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ----- Master Tingkat Kelas -----
        Schema::create('tingkat_kelas', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 10)->unique();                  // "7", "10", "X", dst
            $t->string('nama', 50);                            // "Kelas 7", "Kelas X / 10", dst
            $t->unsignedTinyInteger('nomor');                  // 7..12
            $t->string('jenjang', 10)->nullable();             // SD/SMP/SMA/SMK
            $t->unsignedTinyInteger('urutan')->default(0);
            $t->boolean('is_aktif')->default(true);
            $t->timestamps();
        });

        // ----- Extend login_attempts utk log device -----
        Schema::table('login_attempts', function (Blueprint $t) {
            if (! Schema::hasColumn('login_attempts', 'device_type')) {
                $t->string('device_type', 30)->nullable();     // desktop | mobile | tablet
            }
            if (! Schema::hasColumn('login_attempts', 'browser')) {
                $t->string('browser', 50)->nullable();
            }
            if (! Schema::hasColumn('login_attempts', 'os')) {
                $t->string('os', 50)->nullable();
            }
            if (! Schema::hasColumn('login_attempts', 'network')) {
                $t->string('network', 100)->nullable();        // ISP / network (best-effort)
            }
            if (! Schema::hasColumn('login_attempts', 'attempt_no')) {
                $t->unsignedSmallInteger('attempt_no')->default(1);
            }
        });

        // ----- Current session ID untuk SSO (single device login) -----
        foreach (['users', 'guru', 'siswa'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'current_session_id')) {
                    $t->string('current_session_id', 100)->nullable();
                }
                if (! Schema::hasColumn($table, 'current_device')) {
                    $t->string('current_device', 100)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tingkat_kelas');
        Schema::table('login_attempts', function (Blueprint $t) {
            $t->dropColumn(['device_type','browser','os','network','attempt_no']);
        });
        foreach (['users', 'guru', 'siswa'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['current_session_id', 'current_device']);
            });
        }
    }
};
