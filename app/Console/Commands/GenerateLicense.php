<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class GenerateLicense extends Command
{
    protected $signature = 'app:license
        {value : Tanggal kadaluwarsa (YYYY-MM-DD) atau "unlimited"}
        {--show : Tampilkan ke layar tanpa otomatis menulis .env}
        {--write : Otomatis tulis/replace APP_EXPIRE_DATE di file .env}';

    protected $description = 'Generate kode lisensi terenkripsi untuk APP_EXPIRE_DATE';

    public function handle(): int
    {
        $value = (string) $this->argument('value');

        if ($value !== 'unlimited') {
            try {
                $date = Carbon::parse($value);
                if ($date->isPast()) {
                    $this->warn('Peringatan: tanggal sudah lewat. Lisensi akan langsung kadaluwarsa.');
                }
                $value = $date->format('Y-m-d');
            } catch (\Throwable $e) {
                $this->error('Format tanggal tidak valid. Gunakan YYYY-MM-DD atau "unlimited".');
                return self::INVALID;
            }
        }

        $encrypted = Crypt::encryptString($value);

        $this->line('');
        $this->info('=== Kode Lisensi Berhasil Dibuat ===');
        $this->line('Nilai      : '.$value);
        $this->line('APP_KEY    : '.config('app.key'));
        $this->line('');
        $this->comment('Salin baris berikut ke file .env:');
        $this->line('');
        $this->line('APP_EXPIRE_DATE="'.$encrypted.'"');
        $this->line('');

        if ($this->option('write')) {
            $this->writeToEnv($encrypted);
        } else {
            $this->comment('Jangan lupa: php artisan config:clear');
        }

        return self::SUCCESS;
    }

    protected function writeToEnv(string $encrypted): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            $this->error('.env tidak ditemukan.');
            return;
        }
        $content = file_get_contents($envPath);
        $line = 'APP_EXPIRE_DATE="'.$encrypted.'"';

        if (preg_match('/^APP_EXPIRE_DATE=.*$/m', $content)) {
            $content = preg_replace('/^APP_EXPIRE_DATE=.*$/m', $line, $content);
        } else {
            $content .= PHP_EOL.$line.PHP_EOL;
        }

        file_put_contents($envPath, $content);
        $this->info('✔ .env diperbarui. Menjalankan config:clear...');
        $this->call('config:clear');
    }
}
