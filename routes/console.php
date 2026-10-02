<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Cadangan bila server memasang cron `php artisan schedule:run`. Tanpa cron pun
// ujian yang waktunya habis tetap diselesaikan otomatis oleh aplikasi
// (lihat App\Http\Middleware\SelesaikanUjianKedaluwarsa).
Schedule::command('ujian:selesaikan-kedaluwarsa --terapkan')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    /** @var \Illuminate\Console\Command $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
