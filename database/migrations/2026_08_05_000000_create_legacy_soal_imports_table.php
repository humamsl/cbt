<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak import bank soal dari aplikasi CBT versi lama (lihat
 * App\Console\Commands\ImportSoalLegacy).
 *
 * Menyimpan peta "id di database lama -> id di database ini" supaya:
 *  - command bisa dijalankan ulang tanpa menggandakan soal (idempoten),
 *  - kalau nanti mau ikut mengimpor paket ujian dari dump yang sama, relasi
 *    quiz -> soal masih bisa ditelusuri,
 *  - hasil import bisa ditarik kembali (rollback) per batch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_soal_imports', function (Blueprint $table) {
            $table->id();
            // Nama batch, biasanya nama database sumber — supaya satu instalasi
            // bisa menampung hasil import dari beberapa sekolah/dump berbeda.
            $table->string('batch', 100);
            $table->enum('jenis', ['topic', 'question', 'option']);
            $table->unsignedBigInteger('src_id');
            $table->unsignedBigInteger('new_id');
            $table->timestamps();

            $table->unique(['batch', 'jenis', 'src_id']);
            $table->index(['batch', 'jenis', 'new_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_soal_imports');
    }
};
