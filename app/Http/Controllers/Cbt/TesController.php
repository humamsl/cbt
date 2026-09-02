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
            'violation_sound_enabled' => true,
            'nilai_pengurangan' => 5,
        ]), $user));
    }

    public function store(Request $r)
    {
        $data = $this->v($r);
        // Catat pembuat registrasi (guru) — dipakai Monitoring Ujian untuk
        // membatasi guru agar hanya melihat ujian buatannya sendiri.
        $data['created_by_guru_id'] = $this->shouldScope($r->user()) ? $r->user()->id : null;
        $rombelIds = $data['rombongan_belajar_ids'] ?? [];
        $siswaIds  = $data['siswa_ids'] ?? [];
        unset($data['rombongan_belajar_ids'], $data['siswa_ids']);

        $quiz = DB::transaction(function () use ($data, $rombelIds, $siswaIds) {
            $q = Quiz::create($data);
            $q->rombelTargets()->sync($rombelIds);
            $q->siswaTargets()->sync($siswaIds);
            return $q;
        });

        return redirect()->route('tes.questions', $quiz)->with('success', 'Tes dibuat. Tambahkan soal sekarang.');
    }

    public function edit(Quiz $tes)
    {
        $tes->load('rombelTargets', 'siswaTargets');
        return view('cbt.tes.form', $this->formData($tes, request()->user()));
    }

    public function update(Request $r, Quiz $tes)
    {
        $data = $this->v($r);
        $rombelIds = $data['rombongan_belajar_ids'] ?? [];
        $siswaIds  = $data['siswa_ids'] ?? [];
        unset($data['rombongan_belajar_ids'], $data['siswa_ids']);

        DB::transaction(function () use ($tes, $data, $rombelIds, $siswaIds) {
            $tes->update($data);
            $tes->rombelTargets()->sync($rombelIds);
            $tes->siswaTargets()->sync($siswaIds);
        });

        return redirect()->route('tes.index')->with('success', 'Tes diperbarui.');
    }

    /**
     * AJAX: daftar siswa satu rombel (untuk mode target "Per Siswa" di form).
     * Guru hanya boleh mengambil rombel yang ditugaskan kepadanya.
     */
    public function siswaByRombel(Request $r)
    {
        $r->validate(['rombel' => 'required|integer']);
        $rombelId = (int) $r->rombel;

        if ($this->shouldScope($r->user()) && ! in_array($rombelId, $this->guruRombelIds($r->user()), true)) {
            abort(403, 'Rombel ini tidak ditugaskan kepada Anda.');
        }

        $siswa = \App\Models\SiswaRombel::where('rombongan_belajar_id', $rombelId)
            ->with('siswa:id,nama_siswa,nisn')
            ->get()
            ->pluck('siswa')->filter()
            ->map(fn ($s) => ['id' => $s->id, 'nama' => $s->nama_siswa, 'nisn' => $s->nisn])
            ->sortBy('nama')->values();

        return response()->json($siswa);
    }

    public function destroy(Quiz $tes)
    {
        $tes->delete();
        return back()->with('success', 'Tes dihapus.');
    }

    /**
     * Duplikat registrasi ujian: bikin Quiz baru + salinan soal & target,
     * TANPA menyentuh Quiz sumbernya sama sekali -- ini alternatif yang aman
     * untuk kasus "mau lanjut sesi 2 dgn jadwal beda": daripada mengedit jam
     * tes yang sudah dipakai (attempt sesi 1 tetap ada, tapi historinya jadi
     * susah dibedakan dari sesi 2 di halaman Monitoring), guru duplikat lalu
     * atur jadwal & publish di salinannya. Attempt/jawaban siswa TIDAK ikut
     * disalin -- itu memang punya tes sumber, bukan bagian dari "template".
     *
     * Selalu dibuat sebagai Draft (is_published=false) supaya guru wajib
     * meninjau & menyesuaikan jadwal dulu sebelum salinan ini terlihat siswa.
     */
    public function duplicate(Quiz $tes)
    {
        $tes->load('rombelTargets', 'siswaTargets', 'questions');
        $user = request()->user();

        $baru = DB::transaction(function () use ($tes, $user) {
            $data = $tes->only([
                'description', 'mata_pelajaran_id', 'rombongan_belajar_id',
                'target_mode', 'target_tingkat', 'tahun_ajaran_id', 'tingkat',
                'total_marks', 'pass_marks', 'max_attempts', 'cover_url',
                'duration', 'valid_from', 'valid_upto', 'randomize',
                'randomize_options', 'show_score', 'require_session_token',
                'session_token_id', 'settings', 'protection_enabled',
                'proteksi_mode', 'max_violations', 'violation_sound_enabled',
                'nilai_pengurangan',
            ]);

            $data['name'] = $tes->name.' (Salinan)';
            $data['slug'] = Str::slug($data['name']).'-'.Str::random(5);
            $data['is_published'] = false;
            // Sama seperti store(): pencatat guru mengikuti user yg sedang
            // menduplikat, bukan pembuat aslinya.
            $data['created_by_guru_id'] = $this->shouldScope($user) ? $user->id : null;

            $baru = Quiz::create($data);

            foreach ($tes->questions as $qq) {
                QuizQuestion::create([
                    'quiz_id' => $baru->id,
                    'question_id' => $qq->question_id,
                    'marks' => $qq->marks,
                    'negative_marks' => $qq->negative_marks,
                    'order' => $qq->order,
                ]);
            }

            $baru->rombelTargets()->sync($tes->rombelTargets->pluck('id'));
            $baru->siswaTargets()->sync($tes->siswaTargets->pluck('id'));

            return $baru;
        });

        return redirect()->route('tes.edit', $baru)
            ->with('success', 'Tes berhasil diduplikat sebagai "'.$baru->name.'" (Draft). Periksa & sesuaikan jadwalnya sebelum dipublikasikan.');
    }

    public function questions(Quiz $tes, Request $r)
    {
        $tes->load('questions.question.mapel');
        $available = Question::with('mapel')
            ->when($tes->mata_pelajaran_id, fn ($q) => $q->where('mata_pelajaran_id', $tes->mata_pelajaran_id))
            ->whereNotIn('id', $tes->questions->pluck('question_id'))
            ->when($r->q, fn ($q) => $q->where('title', 'like', "%{$r->q}%"))
            ->tap(fn ($q) => $this->scopeBankSoalForUser($q, $r->user()))
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

        // Hanya rombel TAHUN AJARAN AKTIF (satu TA aktif terbaru, via
        // TahunAjaran::aktif()) — rombel TA lama membuat pilihan dobel
        // (mis. "7-1" muncul dua kali, apalagi bila TA lama lupa
        // dinonaktifkan), dan target ujian baru memang selalu TA berjalan.
        $taAktifId = optional(TahunAjaran::aktif())->id;
        $rombelQuery = RombonganBelajar::with('tahunAjaran', 'jurusan')
            ->when($taAktifId, fn ($q) => $q->where('tahun_ajaran_id', $taAktifId));
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
            // Mode per_siswa: siswa terpilih (utk edit) — id + label untuk chips
            'selectedSiswa' => $item->exists
                ? $item->siswaTargets->map(fn ($s) => ['id' => $s->id, 'nama' => $s->nama_siswa, 'nisn' => $s->nisn])->values()->toArray()
                : [],
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

        // Guru lazim menulis desimal pakai koma (mis. "2,5") -- field ini
        // type="text" (bukan number) justru supaya itu diterima. Normalisasi
        // ke titik SEBELUM validate() supaya rule 'numeric' & cast float di
        // bawah membacanya dengan benar.
        if ($r->filled('nilai_pengurangan')) {
            $r->merge(['nilai_pengurangan' => str_replace(',', '.', (string) $r->input('nilai_pengurangan'))]);
        }

        $data = $r->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'mata_pelajaran_id' => $mapelRule,
            'target_mode' => 'required|in:per_kelas,per_tingkat,per_siswa',
            'rombongan_belajar_ids' => 'required_if:target_mode,per_kelas|array',
            'rombongan_belajar_ids.*' => 'exists:mysql_datacenter.rombongan_belajar,id',
            'siswa_ids' => 'required_if:target_mode,per_siswa|array',
            'siswa_ids.*' => 'exists:mysql_datacenter.siswa,id',
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
            'proteksi_mode' => 'required|in:logout_otomatis,blokir,peringatan,pengurangan_nilai,tanpa_proteksi',
            'max_violations' => 'nullable|integer|min:1|max:99',
            'violation_sound_enabled' => 'nullable|boolean',
            'nilai_pengurangan' => 'nullable|required_if:proteksi_mode,pengurangan_nilai|numeric|min:0.01|max:100',
        ]);

        // Sync legacy fields
        $data['protection_enabled'] = $data['proteksi_mode'] !== 'tanpa_proteksi';

        // Poin pengurangan hanya relevan utk mode 'pengurangan_nilai' — kosongkan
        // di mode lain supaya tidak ada nilai "nyangkut" dari mode sebelumnya.
        // Field ini :disabled di form saat mode lain aktif, jadi browser tidak
        // mengirimkannya sama sekali -- ?? null menjaga baris ini tetap aman
        // walau key-nya tidak ada di $data.
        $data['nilai_pengurangan'] = $data['proteksi_mode'] === 'pengurangan_nilai'
            ? (float) ($data['nilai_pengurangan'] ?? 0) : null;

        // Normalisasi field target per mode — field mode lain dikosongkan
        // supaya tidak ada target "nyangkut" saat admin berganti-ganti mode.
        if ($data['target_mode'] === 'per_tingkat') {
            $data['rombongan_belajar_ids'] = [];
            $data['siswa_ids']             = [];
            $data['rombongan_belajar_id']  = null;
            $data['tingkat']               = null;
            $data['target_tingkat']        = array_values(array_filter(
                array_unique(array_map('intval', $data['target_tingkat'] ?? [])),
                fn ($v) => $v > 0
            ));
        } elseif ($data['target_mode'] === 'per_siswa') {
            $data['rombongan_belajar_ids'] = [];
            $data['rombongan_belajar_id']  = null;
            $data['tingkat']               = null;
            $data['target_tingkat']        = null;
            $data['siswa_ids']             = array_values(array_unique(array_map('intval', $data['siswa_ids'] ?? [])));
        } else {
            $data['siswa_ids']            = [];
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
        $data['violation_sound_enabled'] = $r->boolean('violation_sound_enabled');

        if (! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']).'-'.Str::random(5);
        }
        return $data;
    }
}
