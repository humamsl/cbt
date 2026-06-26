<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder untuk MENGHAPUS semua data aplikasi tanpa drop tabel.
 *
 * Cara pakai:
 *   php artisan db:seed --class=DatabaseTruncateSeeder
 *
 * Catatan:
 *  - Hanya truncate, skema tetap utuh.
 *  - Foreign-key check dimatikan sementara (tabel anak boleh truncate sebelum induk).
 *  - Tabel sistem Laravel (migrations, cache, jobs, sessions, dll.) TIDAK di-truncate.
 *  - Setelah selesai DB benar-benar kosong — admin pun terhapus.
 *    Re-seed via:   php artisan db:seed
 *    atau seeder sekolah:  php artisan db:seed --class=SmpSeeder
 *
 * Opsi melestarikan auth (uncomment di array `$preserve` di bawah).
 */
class DatabaseTruncateSeeder extends Seeder
{
    /**
     * Tabel yang akan di-truncate.
     * Urutan tidak penting karena FK dimatikan sementara.
     */
    protected array $tables = [
        // -------- CBT / Ujian --------
        'exam_violations',
        'quiz_attempt_answers',
        'quiz_attempts',
        'quiz_questions',
        'quiz_rombongan_belajar',
        'quizzes',
        'session_tokens',
        'token_sesi',                  // alias jika ada
        'question_options',
        'questions',
        'question_types',
        'topics',

        // -------- Master Data --------
        'guru_mapel',
        'siswa_rombel',
        'rombongan_belajar',
        'tingkat_kelas',
        'mata_pelajaran',
        'jurusan',
        'tahun_ajaran',
        'sekolah',
        'siswa',
        'guru',

        // -------- Proteksi & Auth --------
        'otp_codes',
        'login_attempts',
        'role_permissions',
        'permissions',
        'roles',
        'users',

        // -------- Aplikasi --------
        'app_settings',
        'log_logins',                  // jika ada
    ];

    /**
     * Tabel yang BOLEH di-skip kalau ingin tetap login setelah truncate.
     * Default: KOSONG (admin akan terhapus juga).
     *
     * Contoh isi kalau mau preserve:
     *   protected array $preserve = ['users', 'roles', 'permissions', 'role_permissions'];
     */
    protected array $preserve = [];

    public function run(): void
    {
        $driver = DB::connection()->getDriverName();
        $this->command?->warn("Driver DB: {$driver}");
        $this->command?->warn('Menghapus seluruh data aplikasi...');

        $this->disableForeignKeyChecks($driver);

        $totalTrunc = 0; $totalSkip = 0;
        foreach ($this->tables as $table) {
            if (in_array($table, $this->preserve, true)) {
                $this->command?->info("  [skip preserve] {$table}");
                $totalSkip++;
                continue;
            }
            if (! Schema::hasTable($table)) {
                $totalSkip++;
                continue; // diam-diam, tidak semua tabel ada di setiap proyek
            }

            try {
                DB::table($table)->truncate();
                $this->command?->info("  [truncated] {$table}");
                $totalTrunc++;
            } catch (\Throwable $e) {
                $this->command?->error("  [gagal] {$table}: ".$e->getMessage());
            }
        }

        $this->enableForeignKeyChecks($driver);

        $this->command?->newLine();
        $this->command?->info("Selesai. {$totalTrunc} tabel di-truncate, {$totalSkip} dilewati.");
        $this->command?->warn('Database sekarang KOSONG. Jalankan ulang seeder untuk mengisi data:');
        $this->command?->line('   php artisan db:seed');
        $this->command?->line('   php artisan db:seed --class=SmpSeeder   (atau SmaSeeder / SmkSeeder)');
    }

    protected function disableForeignKeyChecks(string $driver): void
    {
        match ($driver) {
            'mysql', 'mariadb'  => DB::statement('SET FOREIGN_KEY_CHECKS=0;'),
            'sqlite'            => DB::statement('PRAGMA foreign_keys = OFF;'),
            'pgsql'             => null, // Postgres: pakai TRUNCATE ... CASCADE per tabel — di-handle below
            'sqlsrv'            => DB::statement('EXEC sp_MSforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT ALL"'),
            default             => null,
        };
    }

    protected function enableForeignKeyChecks(string $driver): void
    {
        match ($driver) {
            'mysql', 'mariadb'  => DB::statement('SET FOREIGN_KEY_CHECKS=1;'),
            'sqlite'            => DB::statement('PRAGMA foreign_keys = ON;'),
            'sqlsrv'            => DB::statement('EXEC sp_MSforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT ALL"'),
            default             => null,
        };
    }
}
