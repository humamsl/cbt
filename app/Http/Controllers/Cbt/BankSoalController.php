<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionType;
use App\Models\Topic;
use App\Services\Soal\ExportSoalService;
use App\Services\Soal\ImportSoalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankSoalController extends Controller
{
    use \App\Concerns\ScopedToGuruMapel;

    public function index(Request $r)
    {
        $user = $r->user();
        $query = Question::with('type', 'mapel', 'topic')
            ->when($r->q, fn ($x) => $x->where('title', 'like', "%{$r->q}%")->orWhere('question', 'like', "%{$r->q}%"))
            ->when($r->mapel, fn ($x) => $x->where('mata_pelajaran_id', $r->mapel))
            ->when($r->jenis, fn ($x) => $x->whereHas('type', fn ($t) => $t->where('slug', $r->jenis)));

        $query = $this->scopeBankSoalForUser($query, $user);

        $items = $query->latest()->paginate(15)->withQueryString();

        // Mapel list di filter: untuk guru → hanya mapel yang diajarkan
        $mapelList = $this->shouldScope($user)
            ? MataPelajaran::whereIn('id', $this->guruMapelIds($user))->orderBy('nama_mapel')->get()
            : MataPelajaran::orderBy('nama_mapel')->get();

        return view('cbt.bank-soal.index', [
            'items' => $items,
            'mapelList' => $mapelList,
            'types' => QuestionType::orderBy('id')->get(),
        ]);
    }

    /**
     * Render partial HTML untuk modal preview 1 soal.
     */
    public function preview(Question $bankSoal)
    {
        $bankSoal->load('type', 'mapel', 'topic', 'options');
        return view('cbt.bank-soal._preview-modal', ['q' => $bankSoal]);
    }

    /**
     * Halaman penuh: preview semua soal pada mapel tertentu.
     */
    public function previewMapel(Request $r)
    {
        $user = $r->user();

        $mapelList = $this->shouldScope($user)
            ? MataPelajaran::whereIn('id', $this->guruMapelIds($user))->orderBy('nama_mapel')->get()
            : MataPelajaran::orderBy('nama_mapel')->get();

        $mapel = $r->mapel ? MataPelajaran::find($r->mapel) : null;

        $items = collect();
        if ($mapel) {
            $query = Question::with('type', 'options', 'topic')
                ->where('mata_pelajaran_id', $mapel->id)
                ->when($r->jenis, fn ($x) => $x->whereHas('type', fn ($t) => $t->where('slug', $r->jenis)))
                ->when($r->topik, fn ($x) => $x->where('topic_id', $r->topik))
                ->where('is_active', true);

            $query = $this->scopeBankSoalForUser($query, $user);
            $items = $query->orderBy('title')->get();
        }

        return view('cbt.bank-soal.preview-mapel', [
            'mapel'     => $mapel,
            'mapelList' => $mapelList,
            'items'     => $items,
            'types'     => QuestionType::orderBy('id')->get(),
            'topiks'    => $mapel ? Topic::where('mata_pelajaran_id', $mapel->id)->orderBy('topic')->get() : collect(),
        ]);
    }

    public function create()
    {
        $user = request()->user();
        $mapelList = $this->shouldScope($user)
            ? MataPelajaran::whereIn('id', $this->guruMapelIds($user))->orderBy('nama_mapel')->get()
            : MataPelajaran::orderBy('nama_mapel')->get();

        return view('cbt.bank-soal.form', [
            'item' => new Question(),
            'types' => QuestionType::orderBy('id')->get(),
            'mapel' => $mapelList,
            'topics' => Topic::orderBy('topic')->get(),
            'tingkatList' => \App\Models\TingkatKelas::dropdown(),
            'options' => collect(),
        ]);
    }

    public function store(Request $r)
    {
        DB::transaction(function () use ($r) {
            $data = $this->validateBase($r);
            $data['correct_answer_text'] = $r->input('correct_answer_text');
            $data['case_sensitive'] = $r->boolean('case_sensitive');
            $q = Question::create($data);
            $this->syncOptionsByType($r, $q);
        });
        return redirect()->route('bank-soal.index')->with('success', 'Soal disimpan.');
    }

    public function edit(Question $bankSoal)
    {
        $user = request()->user();
        // Guard: guru hanya boleh edit soal mapel yang dia ajar
        if ($this->shouldScope($user) && ! in_array($bankSoal->mata_pelajaran_id, $this->guruMapelIds($user), true)) {
            abort(403, 'Anda tidak mengajar mapel ini.');
        }

        $bankSoal->load('options', 'type');
        $mapelList = $this->shouldScope($user)
            ? MataPelajaran::whereIn('id', $this->guruMapelIds($user))->orderBy('nama_mapel')->get()
            : MataPelajaran::orderBy('nama_mapel')->get();

        return view('cbt.bank-soal.form', [
            'item' => $bankSoal,
            'types' => QuestionType::orderBy('id')->get(),
            'mapel' => $mapelList,
            'topics' => Topic::orderBy('topic')->get(),
            'tingkatList' => \App\Models\TingkatKelas::dropdown(),
            'options' => $bankSoal->options,
        ]);
    }

    public function update(Request $r, Question $bankSoal)
    {
        DB::transaction(function () use ($r, $bankSoal) {
            $data = $this->validateBase($r);
            $data['correct_answer_text'] = $r->input('correct_answer_text');
            $data['case_sensitive'] = $r->boolean('case_sensitive');
            $bankSoal->update($data);
            $this->syncOptionsByType($r, $bankSoal);
        });
        return redirect()->route('bank-soal.index')->with('success', 'Soal diperbarui.');
    }

    public function destroy(Question $bankSoal)
    {
        $bankSoal->delete();
        return back()->with('success', 'Soal dihapus.');
    }

    /* ===================== IMPORT ===================== */

    public function importForm()
    {
        return view('cbt.bank-soal.import');
    }

    public function importStore(Request $r, ImportSoalService $svc)
    {
        $r->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,docx,doc|max:5120',
        ]);
        $guruId = $r->user()?->user_type === 'guru' ? $r->user()->id : null;
        $result = $svc->import($r->file('file'), $guruId);

        return redirect()->route('bank-soal.import.form')
            ->with('success', "Import selesai: {$result->success} sukses, {$result->failed} gagal.")
            ->with('importErrors', $result->errors);
    }

    public function importTemplate(ExportSoalService $svc)
    {
        return $svc->templateExcel();
    }

    public function importTemplateWord(ExportSoalService $svc)
    {
        return $svc->templateWord();
    }

    /**
     * Upload gambar dari CKEditor / paste-image.
     * Response sesuai SimpleUploadAdapter CKEditor 5:
     *   sukses → { "url": "..." }
     *   gagal  → { "error": { "message": "..." } }
     */
    public function uploadImage(Request $r)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($r->all(), [
                'upload' => 'required|image|mimes:png,jpg,jpeg,gif,webp,svg|max:3072', // 3 MB
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'error' => ['message' => $validator->errors()->first('upload')],
                ], 422);
            }

            $path = $r->file('upload')->store('soal', 'public');
            // Root-relative terhadap base path REQUEST saat ini (mis. "/cbt"), bukan
            // APP_URL statis — supaya benar dijalankan di balik alias nginx apa pun
            // tanpa perlu APP_URL menyertakan sub-path tersebut.
            $url = $r->getBaseUrl().'/storage/'.$path;

            return response()->json([
                'url'  => $url,
                'urls' => ['default' => $url],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => ['message' => 'Gagal upload: '.$e->getMessage()],
            ], 500);
        }
    }

    /* ===================== HELPERS ===================== */

    // (export Bank Soal dipindah ke TesController::exportWord/exportPdf)

    protected function validateBase(Request $r): array
    {
        return $r->validate([
            'title' => 'required|string|max:255',
            'question' => 'required|string',
            'question_type_id' => 'required|exists:question_types,id',
            'mata_pelajaran_id' => 'nullable|exists:mysql_datacenter.mata_pelajaran,id',
            'topic_id' => 'nullable|exists:topics,id',
            'tingkat' => 'nullable|integer|between:1,12',
            'is_active' => 'nullable|boolean',
        ]) + ['is_active' => $r->boolean('is_active', true)];
    }

    protected function syncOptionsByType(Request $r, Question $q): void
    {
        $q->options()->delete();
        $type = QuestionType::find($r->question_type_id);
        $slug = $type?->slug ?? 'pg';

        switch ($slug) {
            case 'pg':
                $correct = (int) $r->input('correct', 0);
                foreach ((array) $r->input('options', []) as $i => $text) {
                    if (trim((string) $text) === '') continue;
                    QuestionOption::create([
                        'question_id' => $q->id, 'option_text' => $text,
                        'is_correct' => $i === $correct, 'order' => $i,
                    ]);
                }
                break;

            case 'pgk':
                $correctSet = array_map('intval', (array) $r->input('correct_multi', []));
                foreach ((array) $r->input('options', []) as $i => $text) {
                    if (trim((string) $text) === '') continue;
                    QuestionOption::create([
                        'question_id' => $q->id, 'option_text' => $text,
                        'is_correct' => in_array($i, $correctSet, true), 'order' => $i,
                    ]);
                }
                break;

            case 'benar-salah':
                $jawaban = strtoupper((string) $r->input('benar_salah_jawaban', 'B'));
                QuestionOption::create([
                    'question_id' => $q->id, 'option_text' => 'Benar',
                    'is_correct' => $jawaban === 'B', 'order' => 0,
                ]);
                QuestionOption::create([
                    'question_id' => $q->id, 'option_text' => 'Salah',
                    'is_correct' => $jawaban === 'S', 'order' => 1,
                ]);
                break;

            case 'fill-blank':
                // Tidak ada options. Jawaban di kolom correct_answer_text.
                break;

            case 'penjodohan':
                $kiri = (array) $r->input('match_left', []);
                $kanan = (array) $r->input('match_right', []);
                foreach ($kiri as $i => $left) {
                    if (trim((string) $left) === '') continue;
                    $right = $kanan[$i] ?? null;
                    QuestionOption::create([
                        'question_id' => $q->id, 'option_text' => $left,
                        'is_left_side' => true, 'pair_group' => $i + 1,
                        'is_correct' => true, 'order' => $i,
                    ]);
                    if ($right && trim($right) !== '') {
                        QuestionOption::create([
                            'question_id' => $q->id, 'option_text' => $right,
                            'is_left_side' => false, 'pair_group' => $i + 1,
                            'is_correct' => true, 'order' => $i,
                        ]);
                    }
                }
                break;
        }
    }
}
