<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pindahkan tabel pivot `quiz_rombongan_belajar` (Quiz <-> RombonganBelajar,
 * relasi belongsToMany di App\Models\Quiz::rombelTargets()) ke database Data
 * Center — SATU sisi dengan RombonganBelajar (yang sekarang connection
 * 'mysql_datacenter').
 *
 * Wajib, karena Eloquent BelongsToMany menjalankan JOIN pivot+tabel-tujuan
 * dalam SATU query pada connection milik model TUJUAN (RombonganBelajar).
 * Kalau pivotnya tetap di database cbt, JOIN itu gagal dengan error
 * "Table 'datacenter.quiz_rombongan_belajar' doesn't exist" — karena MySQL
 * tidak bisa JOIN lintas database dalam satu query.
 *
 * `quiz_id` TIDAK di-FK-kan (quizzes ada di database cbt, beda database),
 * tapi `rombongan_belajar_id` BISA di-FK-kan asli (sama-sama di database
 * Data Center sekarang).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('mysql_datacenter')->hasTable('quiz_rombongan_belajar')) {
            Schema::connection('mysql_datacenter')->create('quiz_rombongan_belajar', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('quiz_id'); // quizzes ada di database cbt, tidak bisa di-FK lintas DB
                $t->foreignId('rombongan_belajar_id')->constrained('rombongan_belajar')->cascadeOnDelete();
                $t->timestamps();
                $t->unique(['quiz_id', 'rombongan_belajar_id']);
            });
        }

        // Salin data lama (kalau tabel lama di database cbt masih ada isinya)
        if (Schema::hasTable('quiz_rombongan_belajar')) {
            DB::table('quiz_rombongan_belajar')->orderBy('id')->get()->each(function ($row) {
                DB::connection('mysql_datacenter')->table('quiz_rombongan_belajar')->insertOrIgnore([
                    'quiz_id' => $row->quiz_id,
                    'rombongan_belajar_id' => $row->rombongan_belajar_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_datacenter')->dropIfExists('quiz_rombongan_belajar');
    }
};
