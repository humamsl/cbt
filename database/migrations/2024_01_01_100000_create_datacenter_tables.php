<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sekolah / profil sekolah
        Schema::create('sekolah', function (Blueprint $table) {
            $table->id();
            $table->string('npsn', 20)->unique();
            $table->string('nama_sekolah');
            $table->string('jenjang', 20)->default('SMA'); // SD, SMP, SMA, SMK
            $table->string('alamat')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('telepon', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('kepala_sekolah')->nullable();
            $table->string('nip_kepala_sekolah', 30)->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        // Tahun ajaran
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id();
            $table->string('kode_tahun_ajaran', 10)->unique(); // e.g. 2526
            $table->string('nama_tahun_ajaran', 30);            // e.g. 2025/2026
            $table->enum('semester', ['Ganjil', 'Genap'])->default('Ganjil');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('is_aktif')->default(false);
            $table->timestamps();
        });

        // Jurusan / program keahlian
        Schema::create('jurusan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_jurusan', 20)->unique();
            $table->string('nama_jurusan');
            $table->string('singkatan', 10)->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // Mata pelajaran
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->string('kode_mapel', 20)->unique();
            $table->string('nama_mapel');
            $table->string('kelompok', 50)->nullable(); // Umum/Kejuruan/Muatan Lokal
            $table->unsignedTinyInteger('tingkat')->nullable(); // 10, 11, 12
            $table->foreignId('jurusan_id')->nullable()->constrained('jurusan')->nullOnDelete();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // Guru (PTK)
        Schema::create('guru', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 30)->unique();
            $table->string('nama_ptk');
            $table->string('email')->nullable();
            $table->string('nomor_hp', 20)->nullable();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('alamat')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('status_kepegawaian')->nullable(); // PNS / GTT / Honorer
            $table->string('foto')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        // Rombongan belajar (kelas)
        Schema::create('rombongan_belajar', function (Blueprint $table) {
            $table->id();
            $table->string('nama_rombel');             // X IPA 1, XI TKJ 2
            $table->unsignedTinyInteger('tingkat');    // 10, 11, 12
            $table->foreignId('jurusan_id')->nullable()->constrained('jurusan')->nullOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->foreignId('wali_kelas_id')->nullable()->constrained('guru')->nullOnDelete();
            $table->unsignedSmallInteger('kapasitas')->default(36);
            $table->timestamps();
            $table->unique(['nama_rombel', 'tahun_ajaran_id']);
        });

        // Siswa
        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->string('nisn', 20)->unique();
            $table->string('nis', 20)->nullable();
            $table->string('nama_siswa');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama')->nullable();
            $table->text('alamat')->nullable();
            $table->string('nomor_hp', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('nomor_hp_ortu', 20)->nullable();
            $table->string('foto')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        // Penempatan siswa di rombel per tahun ajaran
        Schema::create('siswa_rombel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('rombongan_belajar_id')->constrained('rombongan_belajar')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['siswa_id', 'tahun_ajaran_id']);
        });

        // Guru mengajar mapel di rombel
        Schema::create('guru_mapel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('rombongan_belajar_id')->nullable()->constrained('rombongan_belajar')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guru_mapel');
        Schema::dropIfExists('siswa_rombel');
        Schema::dropIfExists('siswa');
        Schema::dropIfExists('rombongan_belajar');
        Schema::dropIfExists('guru');
        Schema::dropIfExists('mata_pelajaran');
        Schema::dropIfExists('jurusan');
        Schema::dropIfExists('tahun_ajaran');
        Schema::dropIfExists('sekolah');
    }
};
