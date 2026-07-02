<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menautkan quiz ke SATU token sesi tertentu (dari fitur Token Sesi yang
     * sudah ada di database/migrations/2024_01_01_200000_create_cbt_tables.php).
     * Sebelumnya `quizzes.require_session_token` sudah ada tapi tidak pernah
     * dipakai/di-cek di alur ujian siswa sama sekali -- kolom ini melengkapinya
     * supaya validasi token di UjianController::start() tahu token MANA yang
     * harus dicocokkan untuk quiz ini (bukan sekadar "ada token aktif apa saja").
     */
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->foreignId('session_token_id')->nullable()->after('require_session_token')
                ->constrained('session_tokens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('session_token_id');
        });
    }
};
