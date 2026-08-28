<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jumlah poin nilai yang dikurangi TIAP kali siswa melakukan pelanggaran,
 * dipakai saat proteksi_mode = 'pengurangan_nilai' (lihat
 * UjianController::finalize()). Beda dari mode lain (blokir/logout_otomatis)
 * yang baru bertindak setelah mencapai batas max_violations — mode ini
 * langsung memotong nilai di setiap pelanggaran, exam tetap boleh lanjut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $t) {
            if (! Schema::hasColumn('quizzes', 'nilai_pengurangan')) {
                $t->decimal('nilai_pengurangan', 8, 2)->nullable()->after('proteksi_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $t) {
            if (Schema::hasColumn('quizzes', 'nilai_pengurangan')) {
                $t->dropColumn('nilai_pengurangan');
            }
        });
    }
};
