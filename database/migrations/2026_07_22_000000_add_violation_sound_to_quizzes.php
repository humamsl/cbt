<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toggle on/off alarm suara saat siswa melakukan pelanggaran.
 *
 * Dipisah dari `protection_enabled` supaya guru bisa tetap merekam pelanggaran
 * (dan menjalankan aksi blokir/logout) TANPA membunyikan alarm — mis. saat satu
 * ruang berisi banyak peserta, alarm dari 1 siswa akan mengganggu yang lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $t) {
            if (! Schema::hasColumn('quizzes', 'violation_sound_enabled')) {
                $t->boolean('violation_sound_enabled')->default(true)->after('max_violations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $t) {
            if (Schema::hasColumn('quizzes', 'violation_sound_enabled')) {
                $t->dropColumn('violation_sound_enabled');
            }
        });
    }
};
