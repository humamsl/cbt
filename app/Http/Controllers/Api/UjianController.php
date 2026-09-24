<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Ujian\ExamScoringService;
use App\Support\ApiHtml;
use Illuminate\Http\Request;

/**
 * Versi JSON dari Cbt\UjianController untuk APLIKASI CBT (siswa, native
 * Flutter). `ping`, `saveAnswer`, `logViolation` TIDAK diduplikasi di sini
 * -- ketiganya sudah 100% JSON di controller web aslinya dan didaftarkan
 * ulang langsung ke controller yang sama di routes/api.php, supaya alur
 * simpan-jawaban & lapor-pelanggaran tidak pernah menyimpang antara web
 * dan aplikasi mobile.
 */
class UjianController extends Controller
{
    public function index(Request $r)
    {
        $siswa = $r->user();

        $quizzes = Quiz::with('mapel')
            ->where('is_published', true)
            ->where(function ($q) { $q->whereNull('valid_upto')->orWhere('valid_upto', '>=', now()); })
            ->untukSiswa($siswa)
            ->withCount('questions')
            ->latest()->paginate(20);

        $statusUjian = QuizAttempt::petaStatusUntukSiswa($quizzes->pluck('id'), $siswa->id);

        return response()->json([
            'data' => $quizzes->map(function (Quiz $q) use ($statusUjian) {
                $status = $statusUjian[$q->id] ?? [];
                return [
                    'id' => $q->id,
                    'name' => $q->name,
                    'mapel' => optional($q->mapel)->nama_mapel,
                    'duration' => $q->duration,
                    'total_questions' => $q->questions_count,
                    'valid_from' => optional($q->valid_from)->toIso8601String(),
                    'valid_upto' => optional($q->valid_upto)->toIso8601String(),
                    'status' => $q->status, // draft|menunggu|berlangsung|selesai (Quiz::getStatusAttribute)
                    'require_session_token' => (bool) $q->require_session_token,
                    'attempt_blocked' => (bool) ($status['attempt_blokir'] ?? null),
                    'attempt_ongoing_id' => optional($status['attempt_sedang'] ?? null)->id,
                    'attempt_done_id' => optional($status['attempt_terbaru_selesai'] ?? null)->id,
                ];
            })->values(),
            'meta' => [
                'current_page' => $quizzes->currentPage(),
                'last_page' => $quizzes->lastPage(),
                'total' => $quizzes->total(),
            ],
        ]);
    }

    public function start(Quiz $quiz, Request $r)
    {
        $siswa = $r->user();

        if (! $quiz->is_published || ! $quiz->ditargetkanUntukSiswa($siswa)) {
            return response()->json(['ok' => false, 'error' => 'not_available', 'message' => 'Ujian ini tidak tersedia untuk kelas/tingkat Anda.'], 403);
        }

        if ($quiz->belum_dimulai) {
            return response()->json([
                'ok' => false, 'error' => 'belum_dimulai',
                'message' => 'Ujian "'.$quiz->name.'" belum dimulai. Ujian dibuka pada '.$quiz->valid_from->format('d M Y H:i').'.',
            ], 422);
        }
        if ($quiz->sudah_berakhir) {
            return response()->json(['ok' => false, 'error' => 'sudah_berakhir', 'message' => 'Waktu ujian "'.$quiz->name.'" sudah berakhir.'], 422);
        }

        // Lihat catatan panjang di Cbt\UjianController::start() soal kenapa
        // filter is_done TIDAK boleh ditambahkan ke query blokir/selesai ini.
        $blocked = QuizAttempt::where('quiz_id', $quiz->id)->where('siswa_id', $siswa->id)->where('is_blocked', true)->first();
        if ($blocked) {
            return response()->json(['ok' => false, 'blocked' => true, 'attempt_id' => $blocked->id, 'reason' => $blocked->blocked_reason], 423);
        }

        $selesai = QuizAttempt::where('quiz_id', $quiz->id)->where('siswa_id', $siswa->id)
            ->where('is_done', true)->where('is_blocked', false)->orderByDesc('id')->first();
        if ($selesai) {
            return response()->json(['ok' => false, 'done' => true, 'attempt_id' => $selesai->id]);
        }

        if ($quiz->require_session_token) {
            $token = strtoupper(trim((string) $r->input('token')));
            $sessionToken = $quiz->sessionToken;
            $valid = $token !== ''
                && $sessionToken
                && $sessionToken->is_active
                && strtoupper($sessionToken->token) === $token
                && (! $sessionToken->valid_from || now()->gte($sessionToken->valid_from))
                && (! $sessionToken->valid_upto || now()->lte($sessionToken->valid_upto));

            if (! $valid) {
                return response()->json(['ok' => false, 'error' => 'token_invalid', 'message' => 'Token sesi tidak valid, sudah tidak berlaku, atau belum diatur oleh admin.'], 422);
            }
        }

        $attempt = QuizAttempt::firstOrCreate(
            ['quiz_id' => $quiz->id, 'siswa_id' => $siswa->id, 'is_done' => false],
            ['time_start' => now(), 'ip_address' => $r->ip(), 'user_agent' => substr((string) $r->userAgent(), 0, 500)]
        );
        if (! $attempt->wasRecentlyCreated) {
            $attempt->forceFill(['ip_address' => $r->ip(), 'user_agent' => substr((string) $r->userAgent(), 0, 500)])->save();
        }

        return response()->json(['ok' => true, 'attempt_id' => $attempt->id]);
    }

    public function show(Quiz $quiz, QuizAttempt $attempt, Request $r)
    {
        abort_unless($attempt->siswa_id === $r->user()->id, 403);

        if ($attempt->is_blocked) {
            return response()->json(['ok' => false, 'blocked' => true, 'reason' => $attempt->blocked_reason], 423);
        }
        if ($attempt->is_done) {
            return response()->json(['ok' => false, 'done' => true]);
        }

        $quiz->load('questions.question.options');

        if ($quiz->randomize) {
            $quiz->setRelation('questions', $quiz->questions->shuffle()->values());
        }
        if ($quiz->randomize_options) {
            foreach ($quiz->questions as $qq) {
                $q = $qq->question;
                if (! $q) continue;
                $opts = $q->options;
                $hasMatchingMeta = $opts->contains(fn ($o) => $o->is_left_side === false || $o->pair_group !== null);
                if ($hasMatchingMeta) continue;
                $q->setRelation('options', $opts->shuffle($attempt->id * 1000 + $q->id)->values());
            }
        }

        $existingAnswers = $attempt->answers->keyBy('quiz_question_id');
        $endsAt = $attempt->time_start->copy()->addMinutes((int) $quiz->duration);
        $protectionEnabled = (bool) ($quiz->protection_enabled ?? true);

        $questions = $quiz->questions->map(function ($qq) use ($existingAnswers) {
            $q = $qq->question;
            $ans = $existingAnswers[$qq->id] ?? null;

            // Soal induknya sudah terhapus/hilang dari database (baris
            // quiz_questions-nya masih ada, jadi bukan sekadar dilewati diam-diam)
            // -- balas placeholder yang jelas, JANGAN akses $q->type dkk di bawah
            // (itu yang sebelumnya bikin request ini 500 "Attempt to read
            // property on null", persis seperti versi web di show.blade.php).
            if (! $q) {
                return [
                    'quiz_question_id' => $qq->id,
                    'marks' => $qq->marks,
                    'type' => null,
                    'title' => null,
                    'question_html' => null,
                    'answerable' => false,
                    'existing_answer' => null,
                    'error' => 'Soal ini tidak dapat dimuat. Silakan hubungi pengawas ujian.',
                ];
            }

            $typeSlug = strtolower((string) (optional($q->type)->slug ?? optional($q->type)->question_type ?? ''));
            $isPenjodohan = $typeSlug === 'penjodohan';
            // `answerable`: false kalau PG/PGK/Penjodohan belum ada opsi sama
            // sekali -- fill-blank SELALU answerable (lihat
            // QuizQuestion::isAnswerable(), satu sumber kebenaran yang sama
            // dipakai versi web) walau guru belum mengisi kunci jawabannya.
            $answerable = $qq->isAnswerable();

            $payload = [
                'quiz_question_id' => $qq->id,
                'marks' => $qq->marks,
                'type' => $typeSlug,
                'title' => $q->title,
                'question_html' => ApiHtml::render($q->question),
                'answerable' => $answerable,
                'existing_answer' => null,
            ];

            if (! $answerable) {
                return $payload;
            }

            if ($isPenjodohan) {
                $payload['left_options'] = $q->options->where('is_left_side', true)->sortBy('order')->values()
                    ->map(fn ($o) => ['id' => $o->id, 'html' => ApiHtml::render($o->option_text)]);
                // Sama seperti tampilan web: opsi kanan Penjodohan SELALU plain
                // text (tidak pernah HTML/KaTeX) -- lihat riset SoalHtml/katex.
                $payload['right_options'] = $q->options->where('is_left_side', false)->sortBy('order')->values()
                    ->map(fn ($o) => ['id' => $o->id, 'text' => strip_tags((string) $o->option_text)]);
                if ($ans) $payload['existing_answer'] = ['match_pairs' => $ans->matchPairs()];
            } elseif ($typeSlug === 'fill-blank' || str_contains($typeSlug, 'fill')) {
                if ($ans) $payload['existing_answer'] = ['answer_text' => $ans->answer_text];
            } elseif ($typeSlug === 'pgk') {
                $payload['options'] = $q->options->map(fn ($o) => ['id' => $o->id, 'html' => ApiHtml::render($o->option_text)]);
                if ($ans) $payload['existing_answer'] = ['question_option_ids' => $ans->selectedOptionIds()];
            } else {
                $payload['options'] = $q->options->map(fn ($o) => ['id' => $o->id, 'html' => ApiHtml::render($o->option_text)]);
                if ($ans) $payload['existing_answer'] = ['question_option_id' => $ans->question_option_id];
            }

            return $payload;
        })->values();

        return response()->json([
            'ok' => true,
            'quiz' => [
                'id' => $quiz->id,
                'name' => $quiz->name,
                'duration' => $quiz->duration,
                'ends_at' => $endsAt->toIso8601String(),
                'protection_enabled' => $protectionEnabled,
                'max_violations' => (int) ($quiz->max_violations ?? ($quiz->settings['max_violations'] ?? 5)),
                'proteksi_mode' => $quiz->proteksi_mode,
                'nilai_pengurangan' => (float) ($quiz->nilai_pengurangan ?? 0),
                'violation_sound_enabled' => $protectionEnabled && (bool) ($quiz->violation_sound_enabled ?? true),
            ],
            'attempt' => [
                'id' => $attempt->id,
                'violation_count' => (int) $attempt->violation_count,
            ],
            'questions' => $questions,
        ]);
    }

    public function submit(Quiz $quiz, QuizAttempt $attempt, Request $r, ExamScoringService $scoring)
    {
        abort_unless($attempt->siswa_id === $r->user()->id, 403);
        if ($attempt->is_blocked) {
            return response()->json(['ok' => false, 'blocked' => true], 423);
        }
        if ($attempt->is_done) {
            return response()->json(['ok' => true, 'attempt_id' => $attempt->id]);
        }

        // Sama seperti versi web (Cbt\UjianController::submit()): siswa wajib
        // menjawab semua soal dulu sebelum submit manual, TAPI submit yang
        // dipicu otomatis oleh timer app saat waktu habis harus tetap lolos
        // apa pun kondisinya -- dicek dari waktu server, bukan flag dari app,
        // supaya tidak bisa dilewati begitu saja dari sisi client.
        $endsAt = $attempt->time_start->copy()->addMinutes((int) $quiz->duration);
        if (now()->lt($endsAt)) {
            $sisa = $scoring->unansweredRequiredCount($quiz, $attempt);
            if ($sisa > 0) {
                return response()->json([
                    'ok' => false,
                    'error' => 'belum_lengkap',
                    'message' => "Masih ada {$sisa} soal yang belum dijawab. Jawab semua soal terlebih dahulu sebelum mengirim.",
                    'unanswered_count' => $sisa,
                ], 422);
            }
        }

        $scoring->finalize($quiz, $attempt, forced: false);

        return response()->json(['ok' => true, 'attempt_id' => $attempt->id]);
    }

    public function result(Quiz $quiz, QuizAttempt $attempt, Request $r)
    {
        abort_unless($attempt->siswa_id === $r->user()->id, 403);

        $attempt->load(['answers.quizQuestion.question.options']);

        $data = [
            'attempt' => [
                'id' => $attempt->id,
                'quiz_name' => $quiz->name,
                'nilai' => $attempt->nilai,
                'correct_count' => $attempt->correct_count,
                'wrong_count' => $attempt->wrong_count,
                'empty_count' => $attempt->empty_count,
                'partial_count' => $attempt->partial_count,
                'is_force_submitted' => (bool) $attempt->is_force_submitted,
                'time_start' => optional($attempt->time_start)->toIso8601String(),
                'time_end' => optional($attempt->time_end)->toIso8601String(),
            ],
        ];

        // Sembunyikan rincian benar/salah per soal kalau guru mematikan
        // "Tampilkan Skor" (show_score) untuk ujian ini -- siswa tetap lihat
        // nilai akhir (dibutuhkan utk alur submit), tapi bukan pembahasannya.
        if ($quiz->show_score) {
            $data['questions'] = $attempt->answers->map(function ($a) {
                $q = optional($a->quizQuestion)->question;
                return [
                    'quiz_question_id' => $a->quiz_question_id,
                    'title' => optional($q)->title,
                    'question_html' => ApiHtml::render(optional($q)->question),
                    'marks' => optional($a->quizQuestion)->marks,
                    'partial_score' => $a->partial_score,
                    'is_correct' => $a->is_correct,
                ];
            })->values();
        }

        return response()->json($data);
    }

    public function riwayat(Request $r)
    {
        $items = QuizAttempt::with('quiz.mapel')
            ->where('siswa_id', $r->user()->id)
            ->where('is_done', true)
            ->latest()->paginate(20);

        return response()->json([
            'data' => $items->map(fn (QuizAttempt $a) => [
                'attempt_id' => $a->id,
                'quiz_id' => $a->quiz_id,
                'quiz_name' => optional($a->quiz)->name,
                'mapel' => optional(optional($a->quiz)->mapel)->nama_mapel,
                'nilai' => $a->nilai,
                'time_end' => optional($a->time_end)->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }
}
