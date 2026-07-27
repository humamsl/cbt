<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hak akses monitoring ujian untuk guru (petugas/proktor).
 *
 * Satu baris = satu pasangan guru ↔ rombel: guru tsb boleh memonitoring
 * semua ujian yang menarget rombel itu (di luar ujian buatannya sendiri,
 * yang selalu boleh ia monitor). Dikelola admin lewat halaman
 * "Setting Akses Monitoring Ujian" di modul Monitoring.
 *
 * guru_id & rombongan_belajar_id merujuk tabel di database Data Center —
 * sengaja TANPA foreign key karena lintas database (pola yang sama dengan
 * migration 2026_07_05_000001_drop_fks_to_datacenter_tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_akses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guru_id');
            $table->unsignedBigInteger('rombongan_belajar_id');
            $table->timestamps();
            $table->unique(['guru_id', 'rombongan_belajar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_akses');
    }
};
