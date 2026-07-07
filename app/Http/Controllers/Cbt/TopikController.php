<?php

namespace App\Http\Controllers\Cbt;

use App\Concerns\ScopedToGuruMapel;
use App\Http\Controllers\Controller;
use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\TingkatKelas;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TopikController extends Controller
{
    use ScopedToGuruMapel;

    public function index(Request $r)
    {
        $user = $r->user();

        $items = Topic::with('mapel')
            ->when($r->q, fn ($x) => $x->where('topic', 'like', "%{$r->q}%"))
            ->when($this->shouldScope($user), function ($q) use ($user) {
                $mapelIds    = $this->guruMapelIds($user);
                $tingkatList = $this->guruTingkatList($user);

                $q->whereIn('mata_pelajaran_id', $mapelIds ?: [0])
                  ->where(function ($x) use ($tingkatList) {
                      $x->whereNull('tingkat')
                        ->when(! empty($tingkatList), fn ($y) => $y->orWhereIn('tingkat', $tingkatList));
                  });
            })
            ->orderBy('topic')->paginate(20)->withQueryString();

        return view('cbt.topik.index', compact('items'));
    }

    public function create(Request $r)
    {
        return view('cbt.topik.form', $this->formData($r->user(), new Topic()));
    }

    public function store(Request $r)
    {
        $data = $this->v($r);
        $data['created_by_guru_id'] = $this->shouldScope($r->user()) ? $r->user()->id : null;
        Topic::create($data);

        return redirect()->route('topik.index')->with('success', 'Topik ditambahkan.');
    }

    public function edit(Request $r, Topic $topik)
    {
        $this->authorizeTopic($r->user(), $topik);
        return view('cbt.topik.form', $this->formData($r->user(), $topik));
    }

    public function update(Request $r, Topic $topik)
    {
        $this->authorizeTopic($r->user(), $topik);
        $topik->update($this->v($r));
        return redirect()->route('topik.index')->with('success', 'Topik diperbarui.');
    }

    public function destroy(Request $r, Topic $topik)
    {
        $this->authorizeTopic($r->user(), $topik);
        $topik->delete();
        return back()->with('success', 'Topik dihapus.');
    }

    /* ---------- helpers ---------- */

    protected function formData($user, Topic $item): array
    {
        $tingkatKosong = false;
        $tingkatBelumAktif = [];

        if ($this->shouldScope($user)) {
            $mapelIds    = $this->guruMapelIds($user);
            $tingkatList = $this->guruTingkatList($user);
            $mapel   = MataPelajaran::whereIn('id', $mapelIds ?: [0])->orderBy('nama_mapel')->get();
            $tingkat = TingkatKelas::aktif()
                        ->whereIn('nomor', $tingkatList ?: [0])
                        ->orderBy('nomor')->get();
            $tingkatKosong = empty($tingkatList);

            // Guru sudah punya rombel (tingkat terdeteksi dari guru_mapel), tapi tidak semuanya
            // muncul di dropdown → berarti nomor tingkat itu belum aktif di master Tingkat Kelas.
            if (! $tingkatKosong) {
                $tingkatBelumAktif = collect($tingkatList)
                    ->diff($tingkat->pluck('nomor'))
                    ->sort()->values()->toArray();
            }
        } else {
            $mapel   = MataPelajaran::orderBy('nama_mapel')->get();
            $tingkat = TingkatKelas::aktif()->orderBy('nomor')->get();
        }

        return compact('item', 'mapel', 'tingkat', 'tingkatKosong', 'tingkatBelumAktif');
    }

    protected function v(Request $r): array
    {
        $user = $r->user();
        $rules = [
            'topic'             => 'required|string|max:255',
            'mata_pelajaran_id' => ['required', 'exists:mysql_datacenter.mata_pelajaran,id'],
            'tingkat'           => 'nullable|integer|between:1,12',
            'parent_id'         => 'nullable|exists:topics,id',
            'is_active'         => 'nullable|boolean',
        ];

        if ($this->shouldScope($user)) {
            $rules['mata_pelajaran_id'][] = Rule::in($this->guruMapelIds($user));
            $rules['tingkat'] = ['required', 'integer', Rule::in($this->guruTingkatList($user))];
        }

        $data = $r->validate($rules) + ['is_active' => $r->boolean('is_active', true)];
        $data['slug'] = Str::slug($data['topic']);

        // pastikan kolom rombongan_belajar_id selalu null (legacy)
        $data['rombongan_belajar_id'] = null;

        return $data;
    }

    protected function authorizeTopic($user, Topic $topik): void
    {
        if (! $this->shouldScope($user)) return;

        $mapelIds    = $this->guruMapelIds($user);
        $tingkatList = $this->guruTingkatList($user);

        $okMapel   = in_array($topik->mata_pelajaran_id, $mapelIds, true);
        $okTingkat = ! $topik->tingkat || empty($tingkatList) || in_array($topik->tingkat, $tingkatList, true);

        if (! $okMapel || ! $okTingkat) {
            abort(403, 'Topik di luar mapel / tingkat yang Anda ajar.');
        }
    }

    /** Daftar tingkat (nomor) yang termasuk dalam rombel-rombel yang diajar guru. */
    protected function guruTingkatList($user): array
    {
        if (! $this->shouldScope($user)) return [];

        return GuruMapel::where('guru_id', $user->id)
            ->with('rombel:id,tingkat')->get()
            ->pluck('rombel.tingkat')->filter()->unique()->values()->toArray();
    }
}
