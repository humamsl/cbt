<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ----- Pivot: quiz ↔ banyak rombel target -----
        Schema::create('quiz_rombongan_belajar', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $t->foreignId('rombongan_belajar_id')->constrained('rombongan_belajar')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['quiz_id', 'rombongan_belajar_id']);
        });

        // ----- Tambah enum mode proteksi di quizzes -----
        Schema::table('quizzes', function (Blueprint $t) {
            if (! Schema::hasColumn('quizzes', 'proteksi_mode')) {
                // logout_otomatis | blokir | peringatan | tanpa_proteksi
                $t->string('proteksi_mode', 30)->default('blokir');
            }
        });

        // ----- Backfill: pindahkan rombongan_belajar_id existing → pivot baru -----
        // Plus set proteksi_mode dari protection_enabled lama
        try {
            $rows = DB::table('quizzes')->whereNotNull('rombongan_belajar_id')->get();
            foreach ($rows as $q) {
                DB::table('quiz_rombongan_belajar')->insertOrIgnore([
                    'quiz_id' => $q->id,
                    'rombongan_belajar_id' => $q->rombongan_belajar_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('quizzes')->where('protection_enabled', true)->update(['proteksi_mode' => 'blokir']);
            DB::table('quizzes')->where('protection_enabled', false)->update(['proteksi_mode' => 'tanpa_proteksi']);
        } catch (\Throwable $e) {
            // tabel mungkin baru — abaikan
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_rombongan_belajar');
        Schema::table('quizzes', function (Blueprint $t) {
            $t->dropColumn('proteksi_mode');
        });
    }
};
