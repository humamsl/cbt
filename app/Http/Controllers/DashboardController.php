<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Siswa;
use App\Models\Question;
use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->user_type ?? 'admin';

        if ($role === 'siswa') {
            $ujianTersedia = Quiz::where('is_published', true)
                ->where(function ($q) {
                    $q->whereNull('valid_upto')->orWhere('valid_upto', '>=', now());
                })
                ->latest()->limit(6)->get();

            // Status attempt siswa ini per quiz (blokir/sedang/selesai) supaya
            // tombol "Mulai Ujian" di view bisa dikunci kalau attempt-nya diblokir.
            $statusUjian = QuizAttempt::petaStatusUntukSiswa($ujianTersedia->pluck('id'), $user->id);

            return view('dashboard.siswa', [
                'siswa' => $user,
                'ujianTersedia' => $ujianTersedia,
                'statusUjian' => $statusUjian,
                'riwayat' => QuizAttempt::with('quiz.mapel')
                    ->where('siswa_id', $user->id)
                    ->latest()->limit(5)->get(),
            ]);
        }

        $stats = [
            'siswa'   => Siswa::count(),
            'guru'    => Guru::count(),
            'mapel'   => MataPelajaran::count(),
            'rombel'  => RombonganBelajar::count(),
            'soal'    => Question::count(),
            'tes'     => Quiz::count(),
            'sedang_ujian' => QuizAttempt::whereNotNull('time_start')
                              ->where('is_done', false)->count(),
            'attempt_hari_ini' => QuizAttempt::whereDate('created_at', today())->count(),
        ];

        $ujianAktif = Quiz::with('mapel', 'rombel')
            ->where('is_published', true)
            ->where(function ($q) { $q->whereNull('valid_upto')->orWhere('valid_upto', '>=', now()); })
            ->latest()->limit(5)->get();

        $hasilTerbaru = QuizAttempt::with('quiz', 'siswa')
            ->where('is_done', true)
            ->latest()->limit(8)->get();

        return view('dashboard.admin', compact('stats', 'ujianAktif', 'hasilTerbaru'));
    }
}
