<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PGK & Penjodohan yang dinilai proporsional bisa menghasilkan status
 * "sebagian benar" (mis. cocok 2 dari 3 pasangan) yang bukan Benar
 * (fraction < 1) ATAU Salah (fraction > 0) menurut correct_count/
 * wrong_count biasa -- tanpa kolom ini, jawaban seperti itu dihitung
 * "Salah" walau nilainya sebagian, membingungkan siswa & guru yang
 * melihat ringkasan Benar/Salah/Kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $t) {
            if (! Schema::hasColumn('quiz_attempts', 'partial_count')) {
                $t->unsignedSmallInteger('partial_count')->default(0)->after('wrong_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $t) {
            if (Schema::hasColumn('quiz_attempts', 'partial_count')) {
                $t->dropColumn('partial_count');
            }
        });
    }
};
