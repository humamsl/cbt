<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit untuk Registrasi Ujian (create/update/delete/duplicate) --
 * dibuat setelah kasus draft ujian guru "hilang" berkali-kali tanpa jejak
 * siapa pelakunya (lihat perbaikan kepemilikan di TesController). Snapshot
 * nama tes & nama pelaku disimpan LANGSUNG di baris log (bukan cuma FK ID)
 * supaya log tetap terbaca walau tes-nya sendiri sudah soft-deleted atau
 * akun pelakunya sudah dihapus/berubah nama di kemudian hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_activity_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('quiz_id')->nullable();
            $t->string('quiz_name');
            $t->string('action', 20); // created, updated, deleted, duplicated
            $t->string('actor_type', 10); // admin, guru
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('actor_name');
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['quiz_id']);
            $t->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_activity_logs');
    }
};
