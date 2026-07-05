<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\RombonganBelajar;
use App\Models\SessionToken;
use App\Models\TahunAjaran;
use App\Models\TingkatKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class TesController extends Controller
{
    use \App\Concerns\ScopedToGuruMapel;

    public function index(Request $r)
    {
        $user = $r->user();
        $query = Quiz::with('mapel', 'rombelTargets', 'tahunAjaran')
            ->withCount('questions', 'attempts')
            ->when($r->q, fn ($x) => $x->where('name', 'like', "%{$r->q}%"));

        $query = $this->scopeQuizForUser($query, $user);

        $items = $query->latest()->paginate(15)->withQueryString();
        return view('cbt.tes.index', compact('items'));
    }

    public function create()
    {
        $user = request()->user();
        return view('cbt.tes.form', $this->formData(new Quiz([
            'valid_from'  => now(),
            'valid_upto'  => now()->addDay(),
            'proteksi_mode' => 'blokir',
            'max_violations' => 5,
        ]), $user));
    }

    public function store(Request $r)
    {
        $data = $this->v($r);
        $rombelIds = $data['rombongan_belajar_ids'] ?? [];
        unset($data['rombongan_belajar_ids']);

        $quiz = DB::transaction(function () use ($data, $rombelIds) {
            $q = Quiz::create($data);
            $q->rombelTargets()->sync($rombelIds);
            return $q;
        });

        return redirect()->route('tes.questions', $quiz)->with('success', 'Tes dibuat. Tambahkan soal sekarang.');
    }

    public function edit(Quiz $tes)
    {
        $tes->load('rombelTargets');
        return view('cbt.tes.form', $this->formData($tes, request()->user()));
    }

    public function update(Request $r, Quiz $tes)
    {
        $data = $this->v($r);
        $rombelIds = $data['rombongan_belajar_ids'] ?? [];
        unset($data['rombongan_belajar_ids']);

        DB::transaction(function () use ($tes, $data, $rombelIds) {
            $tes->update($data);
            $tes->rombelTargets()->sync($rombelIds);
        });

        return redirect()->route('tes.index')->with('success', 'Tes diperbarui.');
    }

    public function destroy(Quiz $tes)
    {
        $tes->delete();
        return back()->with('success', 'Tes dihapus.');
    }

    public function questions(Quiz $tes, Request $r)
    {
        $tes->load('questions.question.mapel');
        $available = Question::with('mapel')
            ->when($tes->mata_pelajaran_id, fn ($q) => $q->where('mata_pelajaran_id', $tes->mata_pelajaran_id))
            ->whereNotIn('id', $tes->questions->pluck('question_id'))
            ->when($r->q, fn ($q) => $q->where('title', 'like', "%{$r->q}%"))
            ->paginate(10)->withQueryString();
        return view('cbt.tes.questions', compact('tes', 'available'));
    }

    public function attachQuestion(Quiz $tes, Request $r)
    {
        $data = $r->validate(['question_id' => 'required|exists:questions,id', 'marks' => 'nullable|numeric|min:0']);
        QuizQuestion::create([
            'quiz_id' => $tes->id,
            'question_id' => $data['question_id'],
            'marks' => $data['marks'] ?? 1,
            'order' => $tes->questions()->max('order') + 1,
        ]);
        $tes->update(['total_marks' => $tes->questions()->sum('marks')]);
        return back()->with('success', 'Soal ditambahkan ke tes.');
    }

    public function detachQuestion(Quiz $tes, QuizQuestion $quizQuestion)
    {
        $quizQuestion->delete();
        $tes->update(['total_marks' => $tes->questions()->sum('marks')]);
        return back()->with('success', 'Soal dihapus dari tes.');
    }

    /* ===================== EXPORT SOAL UJIAN ===================== */

    /**
     * Ambil koleksi Question (Eloquent) dari semua soal yang sudah di-attach ke quiz,
     * urut sesuai urutan QuizQuestion.order.
     * Returnnya WAJIB Eloquent\Collection supaya method seperti load()/loadMissing()
     * di service masih bisa dipanggil.
     */
    protected function questionsFromQuiz(Quiz $tes): \Illuminate\Database\Eloquent\Collection
    {
        $plain = $tes->questions()
            ->with('question.options', 'question.mapel', 'question.type')
            ->orderBy('order')
            ->get()
            ->pluck('question')
            ->filter()
            ->values()
            ->all();

        return new \Illuminate\Database\Eloquent\Collection($plain);
    }

    public function exportWord(Quiz $tes, \App\Services\Soal\ExportSoalService $svc)
    {
        $questions = $this->questionsFromQuiz($tes);
        if ($questions->isEmpty()) {
            return back()->with('error', 'Belum ada soal di tes ini untuk di-export.');
        }
        return $svc->exportWord($questions, $tes->name);
    }

    public function exportPdf(Quiz $tes, Request $r, \App\Services\Soal\ExportSoalService $svc)
    {
        $questions = $this->questionsFromQuiz($tes);
        if ($questions->isEmpty()) {
            return back()->with('error', 'Belum ada soal di tes ini untuk di-export.');
        }
        return $svc->exportPdf($questions, $tes->name, withAnswer: $r->boolean('with_answer'));
    }

    /*    helpers    */

    protected function formData(Quiz $item, $user): array
    {
        $mapelList = $this->shouldScope($user)
            ? MataPelajaran::whereIn('id', $this->guruMapelIds($user))->orderBy('nama_mapel')->get()
            : MataPelajaran::orderBy('nama_mapel')->get();

        $rombelQuery = RombonganBelajar::with('tahunAjaran', 'jurusan');
        if ($this->shouldScope($user)) {
            $rombelQuery->whereIn('id', $this->guruRombelIds($user));
        }

        return [
            'item' => $item,
            'mapel' => $mapelList,
            // Ujian Umum (semua mapel, mata_pelajaran_id kosong) hanya boleh dibuat
            // admin — guru tetap wajib pilih salah satu mapel yang diajarnya.
            'canPilihUjianUmum' => ! $this->shouldScope($user),
            'rombel' => $rombelQuery->orderBy('tingkat')->orderBy('nama_rombel')->get(),
            'tahunAjaran' => TahunAjaran::orderByDesc('id')->get(),
            'tingkatList' => TingkatKelas::aktif()->orderBy('nomor')->get(),
            'selectedRombelIds' => $item->exists ? $item->rombelTargets->pluck('id')->toArray() : [],
            'sessionTokens' => SessionToken::orderByDesc('id')->get(),
        ];
    }

    protected function v(Request $r): array
    {
        // Ujian Umum (semua mapel) hanya boleh dibuat admin — kosongkan
        // mata_pelajaran_id diperbolehkan HANYA untuk admin, guru tetap wajib.
        // PENTING: rule exists: HARUS diberi prefix connection "mysql_datacenter."
        // karena tabel ini sekarang live di database Data Center (lihat
        // App\Models\RombonganBelajar dkk) — tanpa prefix, Laravel diam-diam
        // mengecek tabel lokal cbt yang basi/kosong, sehingga ID yang sebenarnya
        // valid (baru ditambahkan di Data Center) ditolak validasi.
        $mapelRule = $this->shouldScope($r->user())
            ? 'required|exists:mysql_datacenter.mata_pelajaran,id'
            : 'nullable|exists:mysql_datacenter.mata_pelajaran,id';

        $data = $r->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'mata_pelajaran_id' => $mapelRule,
            'target_mode' => 'required|in:per_kelas,per_tingkat',
            'rombongan_belajar_ids' => 'required_if:target_mode,per_kelas|array',
            'rombongan_belajar_ids.*' => 'exists:mysql_datacenter.rombongan_belajar,id',
            'target_tingkat' => 'required_if:target_mode,per_tingkat|array',
            // nullable: select "Pilih Tingkat" tetap ada di DOM (cuma disembunyikan
            // via x-show, bukan dihapus) saat mode "Per Kelas" dipilih, jadi tetap
            // ikut ter-submit dgn nilai kosong "" — harus lolos validasi di sini,
            // nanti di-null-kan eksplisit oleh logika mode per_kelas di bawah.
            'target_tingkat.*' => 'nullable|integer|between:1,12',
            'tahun_ajaran_id' => 'nullable|exists:mysql_datacenter.tahun_ajaran,id',
            'duration' => 'required|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_upto' => 'nullable|date|after_or_equal:valid_from',
            'randomize' => 'nullable|in:0,1',
            'randomize_options' => 'nullable|in:0,1',
            'show_score' => 'nullable|in:0,1',
            'is_published' => 'nullable|boolean',
            'require_session_token' => 'nullable|boolean',
            'session_token_id' => 'nullable|required_if:require_session_token,1|exists:session_tokens,id',
            'proteksi_mode' => 'required|in:logout_otomatis,blokir,peringatan,tanpa_proteksi',
            'max_violations' => 'nullable|integer|min:1|max:99',
        ]);

        // Sync legacy fields
        $data['protection_enabled'] = $data['proteksi_mode'] !== 'tanpa_proteksi';

        // Mode per_tingkat → kosongkan rombel pivot, tingkat utama tidak relevan
        if ($data['target_mode'] === 'per_tingkat') {
            $data['rombongan_belajar_ids'] = [];
            $data['rombongan_belajar_id']  = null;
            $data['tingkat']               = null;
            $data['target_tingkat']        = array_values(array_filter(
                array_unique(array_map('intval', $data['target_tingkat'] ?? [])),
                fn ($v) => $v > 0
            ));
        } else {
            $data['rombongan_belajar_id'] = $data['rombongan_belajar_ids'][0] ?? null;
            $data['target_tingkat']       = null;
            // Tingkat otomatis ikut tingkat rombel pertama (untuk kompatibilitas)
            if (! empty($data['rombongan_belajar_ids'])) {
                $first = \App\Models\RombonganBelajar::find($data['rombongan_belajar_ids'][0]);
                $data['tingkat'] = $first?->tingkat;
            }
        }

        $data['randomize']         = (int) ($r->input('randomize', 0));
        $data['randomize_options'] = (int) ($r->input('randomize_options', 0));
        $data['show_score']        = (int) ($r->input('show_score', 1));
        $data['is_published'] = $r->boolean('is_published');
        $data['require_session_token'] = $r->boolean('require_session_token');
        // Kalau toggle-nya "Tidak", bersihkan pilihan token supaya tidak ada
        // token nyangkut yang tidak pernah dicek (require_session_token=false).
        $data['session_token_id'] = $data['require_session_token'] ? ($data['session_token_id'] ?? null) : null;
        $data['max_violations'] = (int) ($data['max_violations'] ?? 5);

        if (! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']).'-'.Str::random(5);
        }
        return $data;
    }
}
