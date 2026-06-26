<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
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
        $rombelId = $r->integer('rombel');
        $mapelId  = $r->integer('mapel');
        $status   = $r->input('status');     // menunggu | berlangsung | selesai | draft
        $tanggal  = $r->input('tanggal');    // YYYY-MM-DD

        // Query Quiz dengan agregat attempt
        $items = Quiz::with(['mapel', 'rombel'])
            ->withCount([
                'attempts as total_mulai'     => fn ($q) => $q->whereNotNull('time_start'),
                'attempts as total_selesai'   => fn ($q) => $q->where('is_done', true),
                'attempts as total_blokir'    => fn ($q) => $q->where('is_blocked', true),
            ])
            ->when($rombelId, fn ($q) => $q->where('rombongan_belajar_id', $rombelId))
            ->when($mapelId,  fn ($q) => $q->where('mata_pelajaran_id', $mapelId))
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

        // Hitung "total peserta" per quiz (jumlah siswa di rombel target, atau semua siswa kalau quiz tanpa rombel target)
        $rombelMap = RombonganBelajar::withCount(['siswaRombel as total_siswa'])->get()->keyBy('id');
        $totalSiswaAktif = Siswa::where('is_aktif', true)->count();

        // Jumlah pelanggar (siswa yang violation_count > 0) per quiz
        $pelanggarMap = QuizAttempt::select('quiz_id', DB::raw('COUNT(*) as total'))
            ->where('violation_count', '>', 0)
            ->groupBy('quiz_id')->pluck('total', 'quiz_id')->toArray();

        foreach ($items as $q) {
            $q->total_peserta = $q->rombongan_belajar_id
                ? ($rombelMap[$q->rombongan_belajar_id]->total_siswa ?? 0)
                : $totalSiswaAktif;
            $q->total_pelanggar = $pelanggarMap[$q->id] ?? 0;
        }

        return view('cbt.monitoring.index', [
            'items'  => $items,
            'rombels'=> RombonganBelajar::with('tahunAjaran')->orderBy('nama_rombel')->get(),
            'mapels' => MataPelajaran::orderBy('nama_mapel')->get(),
        ]);
    }

    /**
     * Detail per quiz: list peserta + status + aksi (blokir, buka blokir, reset, lihat).
     */
    public function detail(Quiz $quiz, Request $r)
    {
        $quiz->load('mapel', 'rombel', 'rombelTargets');

        // Kumpulkan semua rombel target (pivot baru + legacy field)
        $rombelIds = $quiz->rombelTargets->pluck('id')->toArray();
        if ($quiz->rombongan_belajar_id) $rombelIds[] = $quiz->rombongan_belajar_id;
        $rombelIds = array_unique(array_filter($rombelIds));

        $siswaQuery = Siswa::query();
        if (! empty($rombelIds)) {
            $siswaQuery->whereHas('siswaRombel', fn ($q) => $q->whereIn('rombongan_belajar_id', $rombelIds));
        } else {
            $siswaQuery->where('is_aktif', true);
        }
        $siswas = $siswaQuery->orderBy('nama_siswa')->get();

        // Map attempt per siswa
        $attempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereIn('siswa_id', $siswas->pluck('id'))
            ->get()->keyBy('siswa_id');

        return view('cbt.monitoring.detail', [
            'quiz'     => $quiz,
            'siswas'   => $siswas,
            'attempts' => $attempts,
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
