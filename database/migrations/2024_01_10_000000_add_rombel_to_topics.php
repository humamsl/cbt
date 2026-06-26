<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            if (! Schema::hasColumn('topics', 'rombongan_belajar_id')) {
                $table->foreignId('rombongan_belajar_id')->nullable()
                    ->after('mata_pelajaran_id')
                    ->constrained('rombongan_belajar')->nullOnDelete();
            }
            if (! Schema::hasColumn('topics', 'created_by_guru_id')) {
                $table->foreignId('created_by_guru_id')->nullable()
                    ->after('is_active')
                    ->constrained('guru')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            if (Schema::hasColumn('topics', 'rombongan_belajar_id')) {
                $table->dropConstrainedForeignId('rombongan_belajar_id');
            }
            if (Schema::hasColumn('topics', 'created_by_guru_id')) {
                $table->dropConstrainedForeignId('created_by_guru_id');
            }
        });
    }
};
