<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom:
 *  - target_mode      : 'per_kelas' (default, pakai pivot rombel) | 'per_tingkat' (pakai daftar tingkat)
 *  - target_tingkat   : json array nomor tingkat (mis. [7,8,9]) — dipakai saat per_tingkat
 *
 * (max_attempts & pass_marks tetap ada di skema tapi tidak ditampilkan/dipakai lagi)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('quizzes', 'target_mode')) {
                $table->string('target_mode', 20)->default('per_kelas')->after('rombongan_belajar_id');
            }
            if (! Schema::hasColumn('quizzes', 'target_tingkat')) {
                $table->json('target_tingkat')->nullable()->after('target_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (Schema::hasColumn('quizzes', 'target_mode'))    $table->dropColumn('target_mode');
            if (Schema::hasColumn('quizzes', 'target_tingkat')) $table->dropColumn('target_tingkat');
        });
    }
};
