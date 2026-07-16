<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot target ujian PER SISWA (Quiz <-> Siswa) untuk target_mode 'per_siswa':
 * admin/guru memilih siswa-siswa tertentu (di dalam tingkat & rombel) yang
 * boleh mengerjakan sebuah ujian.
 *
 * Ditaruh di database DATA CENTER — satu sisi dengan tabel `siswa` — dengan
 * alasan yang sama seperti pivot `quiz_rombongan_belajar`: BelongsToMany
 * menjalankan JOIN pivot+tabel-tujuan dalam SATU query pada koneksi model
 * TUJUAN (Siswa @ mysql_datacenter). `quiz_id` tidak bisa di-FK-kan karena
 * `quizzes` hidup di database cbt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('mysql_datacenter')->hasTable('quiz_siswa')) {
            Schema::connection('mysql_datacenter')->create('quiz_siswa', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('quiz_id'); // quizzes di database cbt — tanpa FK lintas DB
                $t->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $t->timestamps();
                $t->unique(['quiz_id', 'siswa_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_datacenter')->dropIfExists('quiz_siswa');
    }
};
