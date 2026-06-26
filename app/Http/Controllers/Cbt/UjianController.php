<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\ExamViolation;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UjianController extends Controller
{
    public function index(Request $r)
    {
        $siswa = $r->user();

        // Ambil rombel siswa di TA aktif (beserta tingkat-nya)
        $rombelRows = $siswa->siswaRombel()
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->with('rombel:id,tingkat')->get();

        $rombelIds = $rombelRows->pluck('rombongan_belajar_id')->filter()->unique()->values()->toArray();
        $tingkatList = $rombelRows->pluck('rombel.tingkat')->filter()->unique()->values()->toArray();

        $quizzes = Quiz::with('mapel')
            ->where('is_published', true)
            ->where(function ($q) { $q->whereNull('valid_upto')->orWhere('valid_upto', '>=', now()); })
            ->where(function ($q) use ($rombelIds, $tingkatList) {
                // Mode per_kelas → match rombel pivot ATAU legacy single rombel
                $q->where(function ($x) use ($rombelIds) {
                    $x->where('target_mode', 'per_kelas')
                      ->where(function ($y) use ($rombelIds) {
                          $y->whereHas('rombelTargets', fn ($z) => $z->whereIn('rombongan_belajar.id', $rombelIds ?: [0]))
                            ->orWhereIn('rombongan_belajar_id', $rombelIds ?: [0]);
                      });
                })
                // Mode per_tingkat → match salah satu tingkat siswa di kolom JSON
                ->orWhere(function ($x) use ($tingkatList) {
                    $x->where('target_mode', 'per_tingkat');
                    if (! empty($tingkatList)) {
                        $x->where(function ($y) use ($tingkatList) {
                            foreach ($tingkatList as $tk) {
                                $y->orWhereJsonContains('target_tingkat', (int) $tk);
                            }
                        });
                    } else {
                        $x->whereRaw('1=0');
                    }
                });
            })
            ->withCount('questions')
            ->latest()->paginate(12);

        return view('cbt.ujian.list', compact('quizzes'));
    }

    public function start(Quiz $quiz, Request $r)
    {
        $siswa = $r->user();

        // Tolak kalau ada attempt terblokir aktif → siswa harus minta buka blokir ke admin
        $blocked = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('siswa_id', $siswa->id)
            ->where('is_blocked', true)
            ->where('is_done', false)
            ->first();
        if ($blocked) {
            return redirect()->route('siswa.ujian.blocked', [$quiz, $blocked]);
        }

        // Lock IP
        $existing = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('siswa_id', $siswa->id)
            ->where('is_done', false)
            ->first();
        if ($existing && $existing->ip_address && $existing->ip_address !== $r->ip()) {
            abort(409, 'Sesi ujian Anda sudah dibuka dari perangkat/IP lain.');
        }

        $attempt = QuizAttempt::firstOrCreate(
            ['quiz_id' => $quiz->id, 'siswa_id' => $siswa->id, 'is_done' => false],
            [
                'time_start' => now(),
                'ip_address' => $r->ip(),
                'user_agent' => substr((string) $r->userAgent(), 0, 500),
            ]
        );

        return redirect()->route('siswa.ujian.show', [$quiz, $attempt]);
    }

    public function show(Quiz $quiz, QuizAttempt $attempt)
    {
        abort_unless($attempt->siswa_id === request()->user()->id, 403);

        if ($attempt->is_blocked) {
            return redirect()->route('siswa.ujian.blocked', [$quiz, $attempt]);
        }
        if ($attempt->is_done) {
            return redirect()->route('siswa.ujian.result', [$quiz, $attempt]);
        }

        $quiz->load('questions.question.options');

        // Acak urutan soal (jika diaktifkan)
        if ($quiz->randomize) {
            $quiz->setRelation('questions', $quiz->questions->shuffle()->values());
        }

        // Acak urutan opsi jawaban (jika diaktifkan)
        // PENTING: kita pakai seed berdasarkan (attempt_id + question_id) supaya urutan
        // konsisten saat siswa refresh halaman / lanjut dari blokir.
        if ($quiz->randomize_options) {
            foreach ($quiz->questions as $qq) {
                $q = $qq->question;
                if (! $q) continue;
                $opts = $q->options;
                // Penjodohan punya is_left_side/pair_group → JANGAN diacak
                $hasMatchingMeta = $opts->contains(fn ($o) => $o->is_left_side === false || $o->pair_group !== null);
                if ($hasMatchingMeta) continue;

                $seed = $attempt->id * 1000 + $q->id;
                $shuffled = $opts->shuffle($seed);
                $q->setRelation('options', $shuffled->values());
            }
        }

        $existingAnswers = $attempt->answers->keyBy('quiz_question_id');
        $endsAt = $attempt->time_start->copy()->addMinutes((int) $quiz->duration);

        $protectionEnabled = (bool) ($quiz->protection_enabled ?? true);
        $maxViolations = (int) ($quiz->max_violations ?? ($quiz->settings['max_violations'] ?? 5));

        return view('cbt.ujian.show', compact(
            'quiz', 'attempt', 'existingAnswers', 'endsAt',
            'protectionEnabled', 'maxViolations'
        ));
    }

    public function blocked(Quiz $quiz, QuizAttempt $attempt)
    {
        abort_unless($attempt->siswa_id === request()->user()->id, 403);
        return view('cbt.ujian.blocked', compact('quiz', 'attempt'));
    }

    public function saveAnswer(Quiz $quiz, QuizAttempt $attempt, Request $r)
    {
        abort_unless($attempt->siswa_id === $r->user()->id, 403);
        if ($attempt->is_blocked || $attempt->is_done) {
            return response()->json(['ok' => false, 'blocked' => true], 423);
        }

        $data = $r->validate([
            'quiz_question_id' => 'required|exists:quiz_questions,id',
            'question_option_id' => 'nullable|exists:question_options,id',
            'answer_text' => 'nullable|string',
        ]);
        QuizAttemptAnswer::updateOrCreate(
            ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $data['quiz_question_id']],
            ['question_option_id' => $data['question_option_id'] ?? null, 'answer_text' => $data['answer_text'] ?? null]
        );
        return response()->json(['ok' => true]);
    }

    /**
     * Catat pelanggaran. Jika ≥ batas → kunci attempt (blokir) dan auto-submit.
     */
    public function logViolation(Quiz $quiz, QuizAttempt $attempt, Request $r)
    {
        abort_unless($attempt->siswa_id === $r->user()->id, 403);
        if ($attempt->is_done || $attempt->is_blocked) {
            return response()->json(['ok' => false, 'blocked' => true], 423);
        }

        $data = $r->validate([
            'type'   => 'required|string|max:40',
            'detail' => 'nullable|string|max:500',
        ]);

        ExamViolation::create([
            'quiz_attempt_id' => $attempt->id,
            'type'   => $data['type'],
            'detail' => $data['detail'] ?? null,
            'ip_address' => $r->ip(),
        ]);

        $count = $attempt->violations()->count();
        $attempt->update(['violation_count' => $count]);

        $threshold = (int) ($quiz->max_violations ?? ($quiz->settings['max_violations'] ?? 5));
        $mode = $quiz->proteksi_mode ?? 'blokir';
        $shouldBlock = false;
        $shouldLogout = false;

        if ($count >= $threshold) {
            switch ($mode) {
                case 'logout_otomatis':
                    // submit otomatis & paksa siswa keluar dari ujian (tanpa block screen)
                    $this->finalize($quiz, $attempt, forced: true);
                    $shouldLogout = true;
                    break;
                case 'blokir':
                    $this->blockAndFinalize($quiz, $attempt, 'Mencapai batas '.$threshold.' pelanggaran (terakhir: '.$data['type'].')');
                    $shouldBlock = true;
                    break;
                case 'peringatan':
                    // tidak ambil aksi otomatis — hanya tampilkan peringatan
                    break;
                case 'tanpa_proteksi':
                    // skip
                    break;
            }
        }

        return response()->json([
            'ok' => true,
            'count' => $count,
            'threshold' => $threshold,
            'mode' => $mode,
            'blocked' => $shouldBlock,
            'logout' => $shouldLogout,
        ]);
    }

    public function submit(Quiz $quiz, QuizAttempt $attempt, Request $r)
    {
        abort_unless($attempt->siswa_id === $r->user()->id, 403);
        if ($attempt->is_blocked) {
            return redirect()->route('siswa.ujian.blocked', [$quiz, $attempt]);
        }
        $this->finalize($quiz, $attempt, forced: false);
        return redirect()->route('siswa.ujian.result', [$quiz, $attempt]);
    }

    public function result(Quiz $quiz, QuizAttempt $attempt)
    {
        abort_unless($attempt->siswa_id === request()->user()->id, 403);
        return view('cbt.ujian.result', compact('quiz', 'attempt'));
    }

    public function riwayat(Request $r)
    {
        $items = QuizAttempt::with('quiz.mapel')
            ->where('siswa_id', $r->user()->id)
            ->latest()->paginate(20);
        return view('cbt.ujian.riwayat', compact('items'));
    }

    /*    internal    */
    /**
     * Bandingkan jawaban siswa dengan kunci jawaban berdasarkan jenis soal.
     *
     * - Pilihan ganda / benar-salah   → bandingkan question_option_id dengan is_correct
     * - Fill the blank                → bandingkan answer_text dengan correct_answer_text
     *                                   - default: case-INsensitive + trim spasi
     *                                   - kalau soal.case_sensitive = true → perlu sama persis huruf besar/kecil
     *                                   - multi-jawaban: pisah pakai "|" di correct_answer_text
     *                                     mis. "Jakarta|DKI Jakarta|Daerah Khusus Ibukota Jakarta"
     */
    protected function isAnswerCorrect($question, $ans): bool
    {
        if (! $question) return false;

        $typeSlug = strtolower((string) (optional($question->type)->slug ?? optional($question->type)->question_type ?? ''));

        // Fill-blank: perbandingan teks
        if ($typeSlug === 'fill-blank' || $typeSlug === 'fill_blank' || str_contains($typeSlug, 'fill')) {
            $student = (string) ($ans->answer_text ?? '');
            $key     = (string) ($question->correct_answer_text ?? '');

            // Daftar jawaban benar (pisahkan dengan | untuk multi-jawaban)
            $accepted = array_filter(array_map('trim', explode('|', $key)), fn ($v) => $v !== '');
            if (empty($accepted)) return false;

            $caseSensitive = (bool) ($question->case_sensitive ?? false);
            $normalize = fn (string $s) => $caseSensitive
                ? trim($s)
                : mb_strtolower(trim($s));

            $studentNorm = $normalize($student);
            foreach ($accepted as $a) {
                if ($normalize($a) === $studentNorm) return true;
            }
            return false;
        }

        // PG / PGK / Benar-salah → cek question_option_id terhadap opsi is_correct
        $correctOption = $question->options->firstWhere('is_correct', true);
        return $correctOption && $correctOption->id === $ans->question_option_id;
    }

    protected function blockAndFinalize(Quiz $quiz, QuizAttempt $attempt, string $reason): void
    {
        DB::transaction(function () use ($quiz, $attempt, $reason) {
            $attempt->update([
                'is_blocked' => true,
                'blocked_at' => now(),
                'blocked_reason' => $reason,
            ]);
            $this->finalize($quiz, $attempt, forced: true);
        });
    }

    protected function finalize(Quiz $quiz, QuizAttempt $attempt, bool $forced = false): void
    {
        if ($attempt->is_done) return;

        DB::transaction(function () use ($quiz, $attempt, $forced) {
            $quiz->load('questions.question.options');
            $score = 0; $correct = 0; $wrong = 0; $empty = 0;

            foreach ($quiz->questions as $qq) {
                $ans = $attempt->answers()->where('quiz_question_id', $qq->id)->first();
                if (! $ans || (! $ans->question_option_id && ! filled($ans->answer_text))) {
                    $empty++; continue;
                }

                $isCorrect = $this->isAnswerCorrect($qq->question, $ans);
                $ans->update(['is_correct' => $isCorrect]);
                if ($isCorrect) { $correct++; $score += $qq->marks; }
                else { $wrong++; }
            }

            $attempt->update([
                'is_done' => true,
                'time_end' => now(),
                'score' => $score,
                'correct_count' => $correct,
                'wrong_count' => $wrong,
                'empty_count' => $empty,
                'is_force_submitted' => $forced,
            ]);
        });
    }
}
