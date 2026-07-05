<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lepas foreign key constraint dari tabel CBT sendiri (topics, questions,
 * quizzes, quiz_attempts, session_tokens, quiz_rombongan_belajar) yang mengarah
 * ke tabel referensi (mata_pelajaran, rombongan_belajar, guru, siswa, tahun_ajaran).
 *
 * WAJIB dijalankan SEBELUM model referensi (Sekolah/TahunAjaran/Jurusan/
 * MataPelajaran/TingkatKelas/RombonganBelajar/Guru/GuruMapel/Siswa/SiswaRombel)
 * dipindah ke connection 'mysql_datacenter' — MySQL tidak mendukung foreign key
 * lintas-database, jadi constraint lama akan gagal begitu tabel yang dirujuk
 * "pindah" ke database lain. Kolom ID-nya TETAP ADA & TETAP TERISI, cuma
 * constraint-nya yang dilepas (relasi Eloquent tetap berfungsi tanpa FK di DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropForeign(['mata_pelajaran_id']);
            $table->dropForeign(['rombongan_belajar_id']);
            $table->dropForeign(['created_by_guru_id']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['mata_pelajaran_id']);
            $table->dropForeign(['created_by_guru_id']);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropForeign(['mata_pelajaran_id']);
            $table->dropForeign(['rombongan_belajar_id']);
            $table->dropForeign(['tahun_ajaran_id']);
            $table->dropForeign(['created_by_guru_id']);
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropForeign(['siswa_id']);
        });

        Schema::table('session_tokens', function (Blueprint $table) {
            $table->dropForeign(['tahun_ajaran_id']);
        });

        Schema::table('quiz_rombongan_belajar', function (Blueprint $table) {
            $table->dropForeign(['rombongan_belajar_id']);
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->foreign('mata_pelajaran_id')->references('id')->on('mata_pelajaran')->nullOnDelete();
            $table->foreign('rombongan_belajar_id')->references('id')->on('rombongan_belajar')->nullOnDelete();
            $table->foreign('created_by_guru_id')->references('id')->on('guru')->nullOnDelete();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('mata_pelajaran_id')->references('id')->on('mata_pelajaran')->nullOnDelete();
            $table->foreign('created_by_guru_id')->references('id')->on('guru')->nullOnDelete();
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->foreign('mata_pelajaran_id')->references('id')->on('mata_pelajaran')->nullOnDelete();
            $table->foreign('rombongan_belajar_id')->references('id')->on('rombongan_belajar')->nullOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->nullOnDelete();
            $table->foreign('created_by_guru_id')->references('id')->on('guru')->nullOnDelete();
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->foreign('siswa_id')->references('id')->on('siswa')->cascadeOnDelete();
        });

        Schema::table('session_tokens', function (Blueprint $table) {
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->nullOnDelete();
        });

        Schema::table('quiz_rombongan_belajar', function (Blueprint $table) {
            $table->foreign('rombongan_belajar_id')->references('id')->on('rombongan_belajar')->cascadeOnDelete();
        });
    }
};
