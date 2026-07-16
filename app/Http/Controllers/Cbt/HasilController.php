<?php

namespace App\Http\Controllers\Cbt;

use App\Concerns\ScopedToGuruMapel;
use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RombonganBelajar;
use App\Models\Sekolah;
use App\Models\TingkatKelas;
use App\Services\Hasil\HasilReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HasilController extends Controller
{
    use ScopedToGuruMapel;

    /* ============================================================
     * INDEX — daftar nilai semua attempt + filter
     * ============================================================ */
    public function index(Request $r)
    {
        $items = $this->buildAttemptQuery($r)->latest('time_end')->paginate(25)->withQueryString();

        return view('cbt.hasil.index', [
            'items'      => $items,
            'quizzes'    => $this->quizListForUser($r->user()),
            'mapelList'  => MataPelajaran::orderBy('nama_mapel')->get(),
            'rombelList' => RombonganBelajar::orderBy('tingkat')->orderBy('nama_rombel')->get(),
            'tingkatList'=> TingkatKelas::aktif()->orderBy('nomor')->get(),
            'filters'    => $this->getFilters($r),
        ]);
    }

    /* ============================================================
     * DETAIL — review jawaban satu attempt
     * ============================================================ */
    public function detail(QuizAttempt $attempt)
    {
        $attempt->load([
            'quiz.mapel', 'siswa',
            'answers.quizQuestion.question.options',
            'answers.option',
        ]);
        return view('cbt.hasil.detail', compact('attempt'));
    }

    /* ============================================================
     * EXPORT NILAI (Excel) — sesuai filter
     * ============================================================ */
    public function exportNilai(Request $r, HasilReportService $svc)
    {
        $attempts = $this->buildAttemptQuery($r)->latest('time_end')->get();

        $mapel = $r->mapel ? optional(MataPelajaran::find($r->mapel))->nama_mapel : 'Semua mapel';
        $target = match (true) {
            (bool) $r->rombel  => 'Rombel ' . optional(RombonganBelajar::find($r->rombel))->nama_rombel,
            (bool) $r->tingkat => 'Tingkat ' . $r->tingkat,
            default            => 'Semua siswa',
        };

        return $svc->exportNilai($attempts, [
            'mapel'  => $mapel,
            'target' => $target,
            'tahun_ajaran' => optional(\App\Models\TahunAjaran::aktif())->nama_tahun_ajaran ?? '-',
        ]);
    }

    /* ============================================================
     * STATISTIK — ringkasan nilai per quiz
     * ============================================================ */
    public function statistik(Request $r)
    {
        $quizzes = $this->quizListForUser($r->user());

        if (! $r->quiz) {
            return view('cbt.hasil.statistik', [
                'quiz'     => null,
                'quizzes'  => $quizzes,
                'stats'    => null,
            ]);
        }

        $quiz  = Quiz::with('mapel', 'questions.question')->findOrFail($r->quiz);
        $stats = $this->computeStatistik($quiz, (int) ($r->kkm ?? 70));

        return view('cbt.hasil.statistik', compact('quiz', 'quizzes', 'stats'));
    }

    /**
     * Export "HASIL NILAI TES" — format lembar analisis hasil ujian per kelas
     * (DATA UMUM + tabel nilai siswa + rekapitulasi + tanda tangan Kepala
     * Sekolah/Guru Mapel), sesuai format cetak yang dipakai sekolah.
     */
    public function exportStatistik(Request $r, HasilReportService $svc)
    {
        $kktp = (int) ($r->kktp ?? $r->kkm ?? 70);

        $quiz = Quiz::with('mapel', 'questions', 'creator', 'rombel', 'tahunAjaran')->findOrFail($r->quiz);

        $attempts = QuizAttempt::with('siswa.rombelSekarang.rombel')
            ->where('quiz_id', $quiz->id)
            ->where('is_done', true)
            ->get()
            ->sortBy(fn ($a) => optional($a->siswa)->nama_siswa)
            ->values();

        $sekolah = Sekolah::first();

        $meta = [
            'nama_sekolah'        => optional($sekolah)->nama_sekolah ?? '-',
            'kota'                => optional($sekolah)->kabupaten ?? '-',
            'mapel'               => optional($quiz->mapel)->nama_mapel ?? '-',
            'kelas_semester_tahun'=> $r->kelas_semester_tahun ?: $this->deriveKelasLabel($quiz, $attempts, $r->semester),
            'nama_tes'            => $r->nama_tes ?: $quiz->name,
            'materi_pokok'        => $r->materi_pokok ?: '-',
            'tujuan_pembelajaran' => $r->tujuan_pembelajaran ?: '-',
            'tanggal_tes'         => $r->tanggal_tes ? Carbon::parse($r->tanggal_tes) : ($quiz->valid_from ?? now()),
            'kktp'                => $kktp,
            'nama_pengajar'       => optional($quiz->creator)->nama_ptk ?? optional($r->user())->nama_ptk ?? '-',
            'nip_pengajar'        => optional($quiz->creator)->nip ?? '-',
            'kepala_sekolah'      => optional($sekolah)->kepala_sekolah ?? '-',
            'nip_kepala_sekolah'  => optional($sekolah)->nip_kepala_sekolah ?? '-',
            'total_marks'         => $quiz->total_marks ?: 100,
        ];

        return $svc->exportStatistik($quiz, $attempts, $meta);
    }

    /** Label "Tingkat-Rombel" dari data quiz atau (fallback) rombel mayoritas peserta. */
    protected function deriveKelasLabel(Quiz $quiz, $attempts, ?string $semester = null): string
    {
        $rombel = $quiz->rombel;
        if (! $rombel) {
            $rombel = $attempts->map(fn ($a) => optional(optional($a->siswa)->rombelSekarang)->rombel)
                ->filter()->first();
        }
        $kelas = $rombel ? ($rombel->tingkat . '-' . $rombel->nama_rombel) : '-';
        $tahun = optional($quiz->tahunAjaran)->nama_tahun_ajaran ?? optional(\App\Models\TahunAjaran::aktif())->nama_tahun_ajaran ?? '-';

        return trim($kelas . ($semester ? '/' . strtoupper($semester) : '') . '/' . $tahun, '/');
    }

    /* ============================================================
     * ANALISIS BUTIR + EVALUASI SOAL
     * ============================================================ */
    public function analisisButir(Request $r)
    {
        $quizzes = $this->quizListForUser($r->user());

        if (! $r->quiz) {
            return view('cbt.hasil.analisis-butir', [
                'quiz'    => null,
                'quizzes' => $quizzes,
                'items'   => [],
            ]);
        }

        $quiz  = Quiz::with('mapel', 'questions.question.options')->findOrFail($r->quiz);
        $items = $this->computeAnalisisButir($quiz);

        return view('cbt.hasil.analisis-butir', compact('quiz', 'quizzes', 'items'));
    }

    public function exportAnalisisButir(Request $r, HasilReportService $svc)
    {
        $quiz  = Quiz::with('mapel', 'questions.question.options')->findOrFail($r->quiz);
        $items = $this->computeAnalisisButir($quiz);
        return $svc->exportAnalisisButir($quiz, $items);
    }

    /* ============================================================
     * SHARED QUERY & SCOPE
     * ============================================================ */
    protected function buildAttemptQuery(Request $r)
    {
        $user = $r->user();
        $q = QuizAttempt::with([
            'quiz.mapel',
            'siswa.rombelSekarang.rombel',
        ])->where('is_done', true);

        // filter pencarian
        if ($r->q) {
            $q->whereHas('siswa', fn ($s) =>
                $s->where('nama_siswa', 'like', "%{$r->q}%")
                  ->orWhere('nisn', 'like', "%{$r->q}%")
            );
        }
        if ($r->quiz)  $q->where('quiz_id', $r->quiz);
        if ($r->mapel) $q->whereHas('quiz', fn ($x) => $x->where('mata_pelajaran_id', $r->mapel));

        // filter rombel siswa (lewat siswa_rombel TA aktif)
        if ($r->rombel) {
            $q->whereHas('siswa.rombelSekarang', fn ($x) =>
                $x->where('rombongan_belajar_id', $r->rombel)
            );
        }

        // filter tingkat (rombel.tingkat)
        if ($r->tingkat) {
            $q->whereHas('siswa.rombelSekarang.rombel', fn ($x) =>
                $x->where('tingkat', $r->tingkat)
            );
        }

        // Guru hanya boleh lihat ujian mapel/rombel yang dia ajar
        if ($this->shouldScope($user)) {
            $mapelIds  = $this->guruMapelIds($user);
            $q->whereHas('quiz', fn ($x) => $x->whereIn('mata_pelajaran_id', $mapelIds ?: [0]));
        }

        return $q;
    }

    protected function quizListForUser($user)
    {
        $q = Quiz::with('mapel')->orderByDesc('id');
        if ($this->shouldScope($user)) {
            $q->whereIn('mata_pelajaran_id', $this->guruMapelIds($user) ?: [0]);
        }
        return $q->limit(100)->get();
    }

    protected function getFilters(Request $r): array
    {
        return [
            'q' => $r->q, 'quiz' => $r->quiz, 'mapel' => $r->mapel,
            'rombel' => $r->rombel, 'tingkat' => $r->tingkat,
        ];
    }

    /* ============================================================
     * COMPUTE: STATISTIK
     * ============================================================ */
    protected function computeStatistik(Quiz $quiz, int $kkm = 70): array
    {
        $attempts = QuizAttempt::with('answers')
            ->where('quiz_id', $quiz->id)
            ->where('is_done', true)->get();

        // Normalkan ke skala 0–100 dulu: kolom score = poin mentah, sedangkan
        // KKM dan seluruh bucket distribusi di bawah mengasumsikan skala 100.
        $totalMarks = (float) ($quiz->total_marks ?? 0);
        $scores = $attempts->pluck('score')
            ->filter(fn ($v) => $v !== null)
            ->map(fn ($v) => $totalMarks > 0 ? round((float) $v / $totalMarks * 100, 1) : (float) $v)
            ->values()->all();
        $n = count($scores);

        $mean   = $n ? array_sum($scores) / $n : 0;
        $max    = $n ? max($scores) : 0;
        $min    = $n ? min($scores) : 0;
        $median = $this->median($scores);
        $stddev = $this->stddev($scores, $mean);
        $passRate = $n ? (count(array_filter($scores, fn ($s) => $s >= $kkm)) / $n * 100) : 0;

        // Distribusi nilai per range (6 bucket utk bar)
        $distribution = [
            '0-49'   => 0, '50-59'  => 0, '60-69'  => 0,
            '70-79'  => 0, '80-89'  => 0, '90-100' => 0,
        ];
        // Distribusi 7 kategori (utk donut chart sesuai konsep)
        $nilaiBuckets = [
            'Sempurna'   => 0, // 100
            'Diatas 90'  => 0, // 90-99
            'Diatas 80'  => 0, // 80-89
            'Diatas 70'  => 0, // 70-79
            'Diatas 60'  => 0, // 60-69
            'Diatas 50'  => 0, // 50-59
            'Dibawah 50' => 0, // < 50
        ];
        foreach ($scores as $s) {
            $key = match (true) {
                $s >= 90 => '90-100', $s >= 80 => '80-89',
                $s >= 70 => '70-79',  $s >= 60 => '60-69',
                $s >= 50 => '50-59',  default  => '0-49',
            };
            $distribution[$key]++;

            $bucket = match (true) {
                $s >= 100 => 'Sempurna',
                $s >= 90  => 'Diatas 90',
                $s >= 80  => 'Diatas 80',
                $s >= 70  => 'Diatas 70',
                $s >= 60  => 'Diatas 60',
                $s >= 50  => 'Diatas 50',
                default   => 'Dibawah 50',
            };
            $nilaiBuckets[$bucket]++;
        }

        // ----- Per soal: hitung benar / salah / kosong -----
        $perSoal = [];
        foreach ($quiz->questions as $idx => $qq) {
            $benar = 0; $salah = 0; $kosong = 0;
            foreach ($attempts as $a) {
                $ans = $a->answers->firstWhere('quiz_question_id', $qq->id);
                if (! $ans || (! $ans->question_option_id && ! filled($ans->answer_text))) {
                    $kosong++;
                } elseif ($ans->is_correct) {
                    $benar++;
                } else {
                    $salah++;
                }
            }
            $perSoal[] = [
                'no'     => $idx + 1,
                'judul'  => optional($qq->question)->title ?? ('Soal ' . ($idx + 1)),
                'benar'  => $benar,
                'salah'  => $salah,
                'kosong' => $kosong,
            ];
        }

        // ----- Status Pengerjaan: target peserta vs yang sudah selesai -----
        $targetPeserta = $this->countTargetPeserta($quiz);
        $statusPengerjaan = [
            'sudah' => $n,
            'belum' => max(0, $targetPeserta - $n),
            'target'=> $targetPeserta,
        ];

        return [
            'total_peserta'   => QuizAttempt::where('quiz_id', $quiz->id)->count(),
            'peserta_selesai' => $n,
            'total_soal'      => $quiz->questions->count(),
            'kkm'             => $kkm,
            'mean'            => $mean,
            'median'          => $median,
            'max'             => $max,
            'min'             => $min,
            'stddev'          => $stddev,
            'pass_rate'       => $passRate,
            'distribution'    => $distribution,
            'nilai_buckets'   => $nilaiBuckets,
            'per_soal'        => $perSoal,
            'status_pengerjaan' => $statusPengerjaan,
        ];
    }

    /** Hitung jumlah siswa yang menjadi target ujian (untuk grafik status pengerjaan). */
    protected function countTargetPeserta(Quiz $quiz): int
    {
        // per_siswa → jumlah siswa yang dipilih langsung di registrasi ujian
        if (($quiz->target_mode ?? 'per_kelas') === 'per_siswa') {
            return $quiz->siswaTargets()->count();
        }

        if (($quiz->target_mode ?? 'per_kelas') === 'per_tingkat') {
            $tingkatList = (array) ($quiz->target_tingkat ?? []);
            if (empty($tingkatList)) return 0;

            return \App\Models\Siswa::whereHas('rombelSekarang.rombel',
                fn ($q) => $q->whereIn('tingkat', $tingkatList)
            )->count();
        }

        // per_kelas
        $rombelIds = $quiz->rombelTargets()->pluck('rombongan_belajar.id')->toArray();
        if (empty($rombelIds) && $quiz->rombongan_belajar_id) {
            $rombelIds = [$quiz->rombongan_belajar_id];
        }
        if (empty($rombelIds)) return 0;

        return \App\Models\Siswa::whereHas('rombelSekarang',
            fn ($q) => $q->whereIn('rombongan_belajar_id', $rombelIds)
        )->count();
    }

    /* ============================================================
     * COMPUTE: ANALISIS BUTIR SOAL
     * ============================================================ */
    protected function computeAnalisisButir(Quiz $quiz): array
    {
        $attempts = QuizAttempt::with('answers')
            ->where('quiz_id', $quiz->id)
            ->where('is_done', true)
            ->orderByDesc('score')->get();

        $n = $attempts->count();
        if ($n === 0) return [];

        // Kelompok atas & bawah 27%
        $groupSize = max(1, (int) ceil($n * 0.27));
        $upper = $attempts->slice(0, $groupSize);
        $lower = $attempts->slice(-$groupSize);

        $items = [];
        foreach ($quiz->questions as $qq) {
            $qid = $qq->id; // quiz_question_id

            $totalBenar = $attempts->sum(
                fn ($a) => $a->answers->where('quiz_question_id', $qid)->where('is_correct', true)->count()
            );
            $benarAtas = $upper->sum(
                fn ($a) => $a->answers->where('quiz_question_id', $qid)->where('is_correct', true)->count()
            );
            $benarBawah = $lower->sum(
                fn ($a) => $a->answers->where('quiz_question_id', $qid)->where('is_correct', true)->count()
            );

            $p = $n > 0 ? $totalBenar / $n : 0; // Tingkat Kesukaran (0..1)
            $d = $groupSize > 0 ? ($benarAtas / $groupSize) - ($benarBawah / $groupSize) : 0;

            $pKat = match (true) {
                $p > 0.70 => 'Mudah',
                $p >= 0.30 => 'Sedang',
                default => 'Sukar',
            };
            $dKat = match (true) {
                $d > 0.70 => 'Sangat Baik',
                $d >= 0.30 => 'Baik',
                $d >= 0.20 => 'Cukup',
                default => 'Jelek',
            };
            $rekomendasi = match (true) {
                $dKat === 'Jelek'                        => 'Dibuang / Ganti',
                $pKat === 'Sukar' || $pKat === 'Mudah'   => 'Perlu Revisi',
                default                                  => 'Diterima',
            };

            // Distribusi pemilih per opsi (untuk soal pilihan ganda saja)
            $distribusiOpsi = [];
            $isPilihan = $qq->question && $qq->question->options->isNotEmpty();
            if ($isPilihan) {
                foreach ($qq->question->options as $opt) {
                    $countOpt = $attempts->sum(
                        fn ($a) => $a->answers->where('quiz_question_id', $qid)
                                              ->where('question_option_id', $opt->id)->count()
                    );
                    $distribusiOpsi[] = [
                        'label'     => $opt->label ?? '-',
                        'text'      => html_entity_decode(strip_tags((string) $opt->option_text)),
                        'is_correct'=> (bool) $opt->is_correct,
                        'count'     => $countOpt,
                        'percent'   => $n ? $countOpt / $n * 100 : 0,
                    ];
                }
            }

            $items[] = [
                'id'              => $qq->id,
                'title'           => optional($qq->question)->title ?? '-',
                'tipe'            => optional($qq->question->type ?? null)->question_type ?? '-',
                'total_benar'     => $totalBenar,
                'total_peserta'   => $n,
                'percent_correct' => $n ? $totalBenar / $n * 100 : 0,
                'p'               => $p,
                'p_kategori'      => $pKat,
                'd'               => $d,
                'd_kategori'      => $dKat,
                'rekomendasi'     => $rekomendasi,
                'opsi'            => $distribusiOpsi,
            ];
        }

        return $items;
    }

    /* ---------- math helpers ---------- */
    protected function median(array $arr): float
    {
        if (empty($arr)) return 0;
        sort($arr);
        $n = count($arr);
        $mid = (int) ($n / 2);
        return $n % 2 ? (float) $arr[$mid] : ($arr[$mid - 1] + $arr[$mid]) / 2;
    }

    protected function stddev(array $arr, float $mean): float
    {
        $n = count($arr);
        if ($n < 2) return 0;
        $sum = 0;
        foreach ($arr as $v) $sum += ($v - $mean) ** 2;
        return sqrt($sum / ($n - 1));
    }
}
