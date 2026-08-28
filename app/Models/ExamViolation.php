<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamViolation extends Model
{
    protected $guarded = ['id'];

    /** Kode `type` mentah dari examProtection.js (resources/js/stores/examProtection.js) → label Indonesia utk Monitoring Ujian. */
    protected const LABELS = [
        'tab_switch'          => 'Pindah tab browser',
        'app_switch'          => 'Pindah / keluar aplikasi (mobile)',
        'window_blur'         => 'Jendela ujian kehilangan fokus',
        'back_forward_cache'  => 'Navigasi back/forward browser',
        'right_click'         => 'Klik kanan',
        'copy'                => 'Copy (salin teks)',
        'paste'               => 'Paste (tempel teks)',
        'cut'                 => 'Cut (potong teks)',
        'blocked_key'         => 'Tombol pintasan terlarang (mis. F12, Ctrl+Shift+I)',
        'devtools'            => 'DevTools browser terdeteksi terbuka',
        'fullscreen_exit'     => 'Keluar dari mode fullscreen',
        'fullscreen_denied'   => 'Browser menolak mode fullscreen',
        'multi_touch'         => 'Sentuhan multi-jari (mobile)',
        'split_screen'        => 'Mode layar terbagi (split-screen)',
        'orientation_change'  => 'Rotasi layar mencurigakan',
    ];

    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
