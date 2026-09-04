<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionType;
use App\Models\QuizQuestion;
use App\Models\Topic;
use App\Services\Soal\ExportSoalService;
use App\Services\Soal\ImageLocalizer;
use App\Services\Soal\ImportSoalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankSoalController extends Controller
{
    use \App\Concerns\ScopedToGuruMapel;

    public function index(Request $r)
    {
        $user = $r->user();

        $query = Question::with('type', 'mapel', 'topic', 'options')
            // Pencarian WAJIB dibungkus grup sendiri: tanpa itu OR-nya "bocor"
            // keluar (AND lebih kuat dari OR di SQL) sehingga soal yang judulnya
            // cocok ikut lolos walau di luar mapel yang diajar guru.
            ->when($r->q, fn ($x) => $x->where(fn ($w) => $w
                ->where('title', 'like', "%{$r->q}%")
                ->orWhere('question', 'like', "%{$r->q}%")))
            ->when($r->mapel, fn ($x) => $x->where('mata_pelajaran_id', $r->mapel))
            // Filter kelas berdiri sendiri (tidak butuh mapel dipilih dulu) —
            // aman karena scopeBankSoalForUser() di bawah tetap membatasi soal
            // ke milik/penugasan guru ini apa pun kombinasi filternya.
            ->when($r->tingkat, fn ($x) => $x->where('tingkat', (int) $r->tingkat))
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
            // Guru tanpa mapel dipilih → gabungan tingkat dari semua mapel yang diajar
            'tingkatList' => $this->tingkatDropdownFor($user, $r->mapel),
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
            $items = $this->previewMapelQuery($r, $mapel)->get();
        }

        return view('cbt.bank-soal.preview-mapel', [
            'mapel'     => $mapel,
            'mapelList' => $mapelList,
            'items'     => $items,
            'types'     => QuestionType::orderBy('id')->get(),
            'topiks'    => $mapel ? Topic::where('mata_pelajaran_id', $mapel->id)->orderBy('topic')->get() : collect(),
            'tingkatList' => $this->tingkatDropdownFor($user, $r->mapel),
        ]);
    }

    /**
     * Export Word semua soal satu mapel — mengikuti filter yang sama persis
     * dengan halaman Preview Mapel (jenis, topik, tingkat).
     */
    public function exportMapelWord(Request $r, ExportSoalService $svc)
    {
        $r->validate(['mapel' => 'required|exists:mysql_datacenter.mata_pelajaran,id']);
        $user = $r->user();

        // Guru hanya boleh export mapel yang diajarnya
        if ($this->shouldScope($user) && ! in_array((int) $r->mapel, $this->guruMapelIds($user), true)) {
            abort(403, 'Anda tidak mengajar mapel ini.');
        }

        $mapel = MataPelajaran::findOrFail($r->mapel);
        $items = $this->previewMapelQuery($r, $mapel)->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'Tidak ada soal untuk di-export sesuai filter.');
        }

        return $svc->exportWord($items, 'Soal '.$mapel->nama_mapel);
    }

    /** Query soal untuk preview/export per mapel (filter jenis, topik, tingkat). */
    protected function previewMapelQuery(Request $r, MataPelajaran $mapel)
    {
        $query = Question::with('type', 'options', 'topic', 'mapel')
            ->where('mata_pelajaran_id', $mapel->id)
            ->when($r->jenis, fn ($x) => $x->whereHas('type', fn ($t) => $t->where('slug', $r->jenis)))
            ->when($r->topik, fn ($x) => $x->where('topic_id', $r->topik))
            ->when($r->tingkat, fn ($x) => $x->where('tingkat', (int) $r->tingkat))
            ->where('is_active', true);

        return $this->scopeBankSoalForUser($query, $r->user())->orderBy('title');
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
            // Guru → opsi tingkat difilter di form sesuai mapel terpilih
            'tingkatByMapel' => $this->shouldScope($user) ? $this->guruMapelTingkatMap($user) : null,
            'options' => collect(),
        ]);
    }

    public function store(Request $r, ImageLocalizer $localizer)
    {
        // Download gambar eksternal SEBELUM transaksi DB — fetch HTTP bisa
        // lama (timeout 20 dtk/gambar), jangan sambil memegang lock transaksi.
        $this->localizeRequestImages($r, $localizer);

        DB::transaction(function () use ($r) {
            $data = $this->validateBase($r);
            $data['correct_answer_text'] = $r->input('correct_answer_text');
            $data['case_sensitive'] = $r->boolean('case_sensitive');
            // WAJIB diisi supaya soal ini terikat ke guru pembuatnya -- tanpa
            // ini created_by_guru_id tetap null (dianggap "bersama"/shared)
            // dan bisa terlihat/dikelola guru LAIN yang mengajar mapel+tingkat
            // yang sama, padahal seharusnya privat milik guru ini saja.
            $data['created_by_guru_id'] = $this->shouldScope($r->user()) ? $r->user()->id : null;
            $q = Question::create($data);
            $this->syncOptionsByType($r, $q);
        });
        return redirect()->route('bank-soal.index')->with('success', 'Soal disimpan.');
    }

    public function edit(Question $bankSoal)
    {
        $user = request()->user();
        // Guard: guru hanya boleh edit soal mapel & tingkat yang dia ajar
        $this->assertBolehKelolaSoal($user, $bankSoal);

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
            'tingkatByMapel' => $this->shouldScope($user) ? $this->guruMapelTingkatMap($user) : null,
            'options' => $bankSoal->options,
        ]);
    }

    public function update(Request $r, Question $bankSoal, ImageLocalizer $localizer)
    {
        $this->assertBolehKelolaSoal($r->user(), $bankSoal);
        $this->assertSoalTidakSedangDipakai($bankSoal);

        $this->localizeRequestImages($r, $localizer);

        DB::transaction(function () use ($r, $bankSoal) {
            $data = $this->validateBase($r);
            $data['correct_answer_text'] = $r->input('correct_answer_text');
            $data['case_sensitive'] = $r->boolean('case_sensitive');
            $bankSoal->update($data);
            $this->syncOptionsByType($r, $bankSoal);
        });
        return redirect()->route('bank-soal.index')->with('success', 'Soal diperbarui.');
    }

    /**
     * Download semua gambar eksternal (hasil copy-paste dari web / screenshot
     * base64) pada input soal & opsi ke storage/soal, lalu tulis ulang src-nya
     * di request supaya yang tersimpan ke DB selalu gambar milik sendiri.
     */
    protected function localizeRequestImages(Request $r, ImageLocalizer $localizer): void
    {
        $merge = [];

        if (is_string($r->input('question'))) {
            $merge['question'] = $localizer->localizeHtml($r->input('question'));
        }

        foreach (['options', 'match_left', 'match_right'] as $f) {
            $val = $r->input($f);
            if (is_array($val)) {
                $merge[$f] = array_map(
                    fn ($t) => is_string($t) ? $localizer->localizeHtml($t) : $t,
                    $val
                );
            }
        }

        if ($merge) $r->merge($merge);
    }

    public function destroy(Question $bankSoal)
    {
        $this->assertBolehKelolaSoal(request()->user(), $bankSoal);
        $this->assertSoalTidakSedangDipakai($bankSoal);

        $bankSoal->delete();
        return back()->with('success', 'Soal dihapus.');
    }

    /**
     * Tolak edit/hapus soal yang masih terpasang di tes yang SUDAH punya
     * siswa mengerjakan (attempt apa pun -- lagi jalan maupun sudah selesai).
     *
     * KENAPA: BankSoalController::update() (lewat syncOptionsByType()) selalu
     * menghapus SEMUA pilihan jawaban lama lalu membuat yang baru dari nol,
     * apa pun field yang sebenarnya diubah guru. quiz_attempt_answers.
     * question_option_id memakai nullOnDelete (lihat migrasi create_cbt_tables)
     * -- bukan dihapus, tapi jawaban siswa yang sudah tersimpan jadi
     * "terputus" (null) begitu opsi lamanya hilang: siswa yang masih
     * mengerjakan akan dinilai pakai kunci jawaban BARU tanpa sadar, dan
     * siswa yang sudah selesai akan terlihat "belum menjawab" soal itu di
     * halaman Lihat Jawaban walau nilai akhirnya sendiri tidak berubah.
     * Menghapus soal (destroy) lebih parah lagi: soalnya jadi soft-deleted
     * sehingga $qq->question bernilai null dan me-crash halaman ujian siswa
     * yang masih mengerjakan, serta membuat soal itu otomatis dianggap SALAH
     * untuk semua peserta saat dinilai.
     *
     * Kalau soal ini terpasang di tes tapi BELUM ada siswa yang mengerjakan
     * sama sekali, edit/hapus tetap aman dan diizinkan.
     */
    protected function assertSoalTidakSedangDipakai(Question $soal): void
    {
        $terpakai = QuizQuestion::where('question_id', $soal->id)
            ->whereHas('quiz.attempts')
            ->with('quiz:id,name')
            ->first();

        if ($terpakai) {
            $pesan = 'Soal ini tidak bisa diedit/dihapus: sudah dipakai di tes "'.$terpakai->quiz->name.'" yang sudah ada siswa mengerjakan -- mengubahnya bisa merusak jawaban & nilai yang sudah tercatat. Tunggu tes tersebut selesai, atau lepas dulu soal ini dari tes itu kalau memang belum ada yang mengerjakan.';

            abort(back()->with('error', $pesan));
        }
    }

    /**
     * Hapus beberapa soal sekaligus. Setiap ID tetap dicek lewat guard yang
     * sama dengan destroy() satu-per-satu -- supaya guru tidak bisa
     * menghapus soal guru lain hanya dengan menyisipkan ID-nya di request.
     */
    public function bulkDestroy(Request $r)
    {
        $data = $r->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:questions,id',
        ]);
        $user = $r->user();

        $questions = Question::whereIn('id', $data['ids'])->get();

        // Guard dicek untuk SEMUA soal dulu sebelum ada yang dihapus -- supaya
        // kalau satu saja bukan hak guru ini, seluruh batch batal (bukan
        // hapus-sebagian) dan errornya jelas bukan "berhasil separuh".
        foreach ($questions as $q) {
            $this->assertBolehKelolaSoal($user, $q);
            $this->assertSoalTidakSedangDipakai($q);
        }

        DB::transaction(function () use ($questions) {
            foreach ($questions as $q) $q->delete();
        });

        return back()->with('success', count($questions).' soal dihapus.');
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

        try {
            $result = $svc->import($r->file('file'), $guruId);
        } catch (\Throwable $e) {
            // Kegagalan DI SINI beda dari kegagalan per-baris (yang sudah
            // ditangkap satu-satu di ImportSoalService::persist() dan tidak
            // pernah nyampe ke sini) -- ini berarti SELURUH file gagal
            // dibaca sebelum sempat diurai jadi baris soal. Penyebab paling
            // umum: file .docx berisi rumus matematika yang dibuat lewat
            // Equation Editor bawaan Word (Insert > Equation, elemen XML
            // <m:oMath>) -- PHPWord (library pembaca .docx yang dipakai di
            // sini) tidak mendukungnya dan gagal total membaca filenya.
            // Tanpa try/catch ini, exception itu tembus jadi halaman 500
            // mentah yang tidak menjelaskan apa-apa ke guru.
            $pesan = str_contains($e->getMessage(), 'oMath')
                ? 'Gagal membaca file Word: dokumen ini mengandung rumus matematika yang dibuat lewat Equation Editor (menu Insert > Equation di Word), dan format itu tidak didukung sistem import. Ganti rumusnya dengan mengetik teks biasa (mis. pecahan ditulis "1/2") atau simbol Unicode (½, √, ×, π, dst), lalu upload ulang filenya.'
                : 'Gagal membaca file: '.$e->getMessage().'. Pastikan formatnya sesuai contoh template, lalu coba lagi.';

            return redirect()->route('bank-soal.import.form')->with('error', $pesan);
        }

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
        $data = $r->validate([
            'title' => 'required|string|max:255',
            'question' => 'required|string',
            'question_type_id' => 'required|exists:question_types,id',
            'mata_pelajaran_id' => 'nullable|exists:mysql_datacenter.mata_pelajaran,id',
            'topic_id' => 'required|exists:topics,id',
            'tingkat' => 'nullable|integer|between:1,12',
            'is_active' => 'nullable|boolean',
        ]) + ['is_active' => $r->boolean('is_active', true)];

        // Guru hanya boleh menyimpan soal untuk PASANGAN mapel+tingkat yang
        // diajarnya — dropdown di form memang sudah dibatasi, tapi tetap
        // divalidasi di server supaya tidak bisa diakali lewat inspect
        // element / request manual.
        $user = $r->user();
        if ($this->shouldScope($user)) {
            $map = $this->guruMapelTingkatMap($user);
            $mapelId = (int) ($data['mata_pelajaran_id'] ?? 0);

            if (! $mapelId || ! array_key_exists($mapelId, $map)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'mata_pelajaran_id' => 'Pilih mata pelajaran yang Anda ajarkan.',
                ]);
            }

            $allowed = $map[$mapelId];
            if (! empty($data['tingkat']) && ! empty($allowed)
                && ! in_array((int) $data['tingkat'], $allowed, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'tingkat' => 'Anda tidak mengajar mapel ini di tingkat kelas '.$data['tingkat']
                        .'. Tingkat yang diperbolehkan: '.implode(', ', $allowed).'.',
                ]);
            }
        }

        return $data;
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
