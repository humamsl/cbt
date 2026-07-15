<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    /**
     * Halaman utama: tabel agregat per UJIAN.
     * Kolom: judul | kelas | mapel | waktu | status | total peserta | berhasil mengerjakan | selesai mengerjakan | aksi
     */
    public function index(Request $r)
    {
        $kelas   = $r->input('rombel');   // "" | "t7" (tingkat 7) | "<rombel_id>"
        $mapel   = $r->input('mapel');    // "" | "umum" (tanpa mapel) | "<mapel_id>"
        $status  = $r->input('status');   // menunggu | berlangsung | selesai | draft
        $tanggal = $r->input('tanggal');  // YYYY-MM-DD

        // Query Quiz dengan agregat attempt
        $items = Quiz::with(['mapel', 'rombel', 'rombelTargets'])
            ->withCount([
                'attempts as total_mulai'     => fn ($q) => $q->whereNotNull('time_start'),
                'attempts as total_selesai'   => fn ($q) => $q->where('is_done', true),
                'attempts as total_blokir'    => fn ($q) => $q->where('is_blocked', true),
            ])
            // Filter Kelas: dukung PILIH TINGKAT ("t7") maupun rombel tunggal.
            // Quiz cocok bila: legacy rombongan_belajar_id match, ATAU ada di
            // pivot quiz_rombongan_belajar (dibaca dari koneksi Data Center —
            // whereHas lintas DB membaca tabel lokal basi), ATAU mode
            // per_tingkat yang menarget tingkat tsb.
            ->when($kelas, function ($q) use ($kelas) {
                if (str_starts_with($kelas, 't')) {
                    $tingkat   = (int) substr($kelas, 1);
                    $rombelIds = RombonganBelajar::where('tingkat', $tingkat)->pluck('id')->all();
                } else {
                    $rombelIds = [(int) $kelas];
                    $tingkat   = (int) optional(RombonganBelajar::find((int) $kelas))->tingkat;
                }

                $pivotQuizIds = empty($rombelIds) ? [] : DB::connection('mysql_datacenter')
                    ->table('quiz_rombongan_belajar')
                    ->whereIn('rombongan_belajar_id', $rombelIds)
                    ->pluck('quiz_id')->unique()->values()->all();

                $q->where(function ($x) use ($rombelIds, $pivotQuizIds, $tingkat) {
                    $x->whereIn('rombongan_belajar_id', $rombelIds ?: [0])
                      ->orWhereIn('quizzes.id', $pivotQuizIds ?: [0]);
                    if ($tingkat) {
                        $x->orWhere(function ($y) use ($tingkat) {
                            $y->where('target_mode', 'per_tingkat')
                              ->where(fn ($z) => $z->whereJsonContains('target_tingkat', $tingkat)
                                                   ->orWhereJsonContains('target_tingkat', (string) $tingkat));
                        });
                    }
                });
            })
            // Filter Mapel: "umum" = Ujian Umum (tanpa mata pelajaran)
            ->when($mapel === 'umum', fn ($q) => $q->whereNull('mata_pelajaran_id'))
            ->when($mapel && $mapel !== 'umum', fn ($q) => $q->where('mata_pelajaran_id', (int) $mapel))
            ->when($tanggal, function ($q) use ($tanggal) {
                $q->whereDate('valid_from', '<=', $tanggal)
                  ->where(function ($x) use ($tanggal) {
                      $x->whereNull('valid_upto')->orWhereDate('valid_upto', '>=', $tanggal);
                  });
            })
            ->latest('id')->paginate(15)->withQueryString();

        // Filter status manual (karena status adalah accessor)
        if ($status) {
            $filtered = $items->getCollection()->filter(fn ($q) => $q->status === $status)->values();
            $items->setCollection($filtered);
        }

        // Hitung "total peserta" per quiz sesuai TARGET-nya (bukan pukul rata
        // semua siswa sekolah): per_tingkat → siswa rombel TA aktif pada
        // tingkat tsb; pivot multi-rombel → jumlah siswa rombel-rombel itu;
        // legacy single rombel; fallback terakhir barulah seluruh siswa aktif.
        $rombelMap = RombonganBelajar::withCount(['siswaRombel as total_siswa'])->get()->keyBy('id');
        $taAktifId = optional(TahunAjaran::aktif())->id;
        $rombelAktif = $rombelMap->filter(fn ($rb) => $rb->tahun_ajaran_id === $taAktifId);
        $totalSiswaAktif = Siswa::where('is_aktif', true)->count();

        // Jumlah pelanggar (siswa yang violation_count > 0) per quiz
        $pelanggarMap = QuizAttempt::select('quiz_id', DB::raw('COUNT(*) as total'))
            ->where('violation_count', '>', 0)
            ->groupBy('quiz_id')->pluck('total', 'quiz_id')->toArray();

        foreach ($items as $q) {
            if ($q->target_mode === 'per_tingkat' && ! empty($q->target_tingkat)) {
                $target = array_map('intval', (array) $q->target_tingkat);
                $q->total_peserta = $rombelAktif
                    ->filter(fn ($rb) => in_array((int) $rb->tingkat, $target, true))
                    ->sum('total_siswa');
            } elseif ($q->rombelTargets->isNotEmpty()) {
                $q->total_peserta = $q->rombelTargets
                    ->sum(fn ($rb) => $rombelMap[$rb->id]->total_siswa ?? 0);
            } elseif ($q->rombongan_belajar_id) {
                $q->total_peserta = $rombelMap[$q->rombongan_belajar_id]->total_siswa ?? 0;
            } else {
                $q->total_peserta = $totalSiswaAktif;
            }
            $q->total_pelanggar = $pelanggarMap[$q->id] ?? 0;
        }

        $rombels = RombonganBelajar::with('tahunAjaran')->orderBy('nama_rombel')->get();

        return view('cbt.monitoring.index', [
            'items'   => $items,
            'rombels' => $rombels,
            // Daftar tingkat untuk optgroup "Per Tingkat" pada filter Kelas
            'tingkats'=> $rombels->pluck('tingkat')->filter()->map(fn ($t) => (int) $t)->unique()->sort()->values(),
            'mapels'  => MataPelajaran::orderBy('nama_mapel')->get(),
        ]);
    }

    /**
     * Detail per quiz: list peserta + status + aksi (blokir, buka blokir, reset, lihat).
     */
    public function detail(Quiz $quiz, Request $r)
    {
        $quiz->load('mapel', 'rombel', 'rombelTargets');

        // ===== Rombel target quiz =====
        // per_tingkat → semua rombel TA aktif pada tingkat tsb;
        // per_kelas   → rombel pivot + legacy single field.
        if ($quiz->target_mode === 'per_tingkat') {
            $targetRombels = RombonganBelajar::whereIn('tingkat', (array) ($quiz->target_tingkat ?? []))
                ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
                ->orderBy('nama_rombel')->get();
        } else {
            $ids = $quiz->rombelTargets->pluck('id')->toArray();
            if ($quiz->rombongan_belajar_id) $ids[] = $quiz->rombongan_belajar_id;
            $ids = array_unique(array_filter($ids));
            $targetRombels = empty($ids)
                ? collect()
                : RombonganBelajar::whereIn('id', $ids)->orderBy('nama_rombel')->get();
        }
        $rombelIds = $targetRombels->pluck('id')->all();

        // ===== Filter dari UI: per kelas + cari nama/NISN =====
        $rombelFilter = $r->integer('rombel');
        // Hanya terima filter yang memang bagian dari target quiz ini
        if ($rombelFilter && ! empty($rombelIds) && ! in_array($rombelFilter, $rombelIds)) {
            $rombelFilter = null;
        }
        $search = trim((string) $r->input('q'));

        $siswaQuery = Siswa::query()
            // rombel siswa di TA aktif — untuk kolom "Kelas" & pengurutan per kelas
            ->with(['siswaRombel' => fn ($q) => $q
                ->whereHas('tahunAjaran', fn ($x) => $x->where('is_aktif', true))
                ->with('rombel:id,nama_rombel,tingkat')]);

        if ($rombelFilter) {
            $siswaQuery->whereHas('siswaRombel', fn ($q) => $q->where('rombongan_belajar_id', $rombelFilter));
        } elseif (! empty($rombelIds)) {
            $siswaQuery->whereHas('siswaRombel', fn ($q) => $q->whereIn('rombongan_belajar_id', $rombelIds));
        } else {
            $siswaQuery->where('is_aktif', true);
        }

        if ($search !== '') {
            $siswaQuery->where(fn ($q) => $q
                ->where('nama_siswa', 'like', "%{$search}%")
                ->orWhere('nisn', 'like', "%{$search}%"));
        }

        $siswas = $siswaQuery->get();

        // Nama kelas per siswa, lalu urutkan per kelas → nama (bukan acak lagi)
        foreach ($siswas as $s) {
            $s->nama_kelas = optional($s->siswaRombel->first()?->rombel)->nama_rombel ?? '—';
        }
        $siswas = $siswas->sortBy([['nama_kelas', 'asc'], ['nama_siswa', 'asc']])->values();

        // Map attempt per siswa (relasi quiz di-set manual supaya accessor
        // `nilai` tidak lazy-load quiz yang sama berulang-ulang per baris)
        $attempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereIn('siswa_id', $siswas->pluck('id'))
            ->get()->each->setRelation('quiz', $quiz)->keyBy('siswa_id');

        return view('cbt.monitoring.detail', [
            'quiz'          => $quiz,
            'siswas'        => $siswas,
            'attempts'      => $attempts,
            'targetRombels' => $targetRombels,
            'rombelFilter'  => $rombelFilter,
            'search'        => $search,
        ]);
    }

    public function block(QuizAttempt $attempt, Request $r)
    {
        $reason = $r->input('reason', 'Diblokir manual oleh admin');
        $attempt->update([
            'is_blocked' => true,
            'blocked_at' => now(),
            'blocked_reason' => $reason,
        ]);
        return back()->with('success', "Ujian {$attempt->siswa->nama_siswa} diblokir.");
    }

    public function unblock(QuizAttempt $attempt)
    {
        $attempt->update([
            'is_blocked'         => false,
            'blocked_at'         => null,
            'blocked_reason'     => null,
            'is_done'            => false,
            'is_force_submitted' => false,
            'time_end'           => null,
            'score'              => null,
            'correct_count'      => 0,
            'wrong_count'        => 0,
            'empty_count'        => 0,
        ]);
        return back()->with('success', "Blokir ujian {$attempt->siswa->nama_siswa} dibuka. Jawaban & pelanggaran dipertahankan.");
    }

    public function resetAttempt(QuizAttempt $attempt)
    {
        $name = $attempt->siswa->nama_siswa ?? '-';
        DB::transaction(function () use ($attempt) {
            $attempt->violations()->delete();
            $attempt->answers()->delete();
            $attempt->delete();
        });
        return back()->with('success', "Attempt {$name} direset. Siswa dapat mengerjakan ulang.");
    }

    public function lihat(QuizAttempt $attempt)
    {
        return redirect()->route('hasil.detail', $attempt);
    }
}
