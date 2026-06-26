<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;
use App\Services\Master\SiswaExcelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiswaController extends Controller
{
    public function index(Request $r)
    {
        $items = Siswa::with(['rombelSekarang.rombel'])
            ->when($r->q, function ($x) use ($r) {
                $x->where('nama_siswa', 'like', "%{$r->q}%")
                  ->orWhere('nisn', 'like', "%{$r->q}%")
                  ->orWhere('nis', 'like', "%{$r->q}%");
            })
            ->orderBy('nama_siswa')
            ->paginate(25)->withQueryString();
        return view('datacenter.siswa.index', compact('items'));
    }

    public function create()
    {
        return view('datacenter.siswa.form', [
            'item' => new Siswa(),
            'rombel' => RombonganBelajar::with('tahunAjaran')->get(),
        ]);
    }

    public function store(Request $r)
    {
        DB::transaction(function () use ($r) {
            $data = $this->v($r);
            $data['password'] = $data['password'] ?? '12345678'; //password default untuk siswa baru
            $siswa = Siswa::create($data);
            $this->syncRombel($r, $siswa);
        });
        return redirect()->route('siswa.index')->with('success', 'Data siswa ditambahkan.');
    }

    public function edit(Siswa $siswa)
    {
        $siswa->load('rombelSekarang');
        return view('datacenter.siswa.form', [
            'item' => $siswa,
            'rombel' => RombonganBelajar::with('tahunAjaran')->get(),
        ]);
    }

    public function update(Request $r, Siswa $siswa)
    {
        DB::transaction(function () use ($r, $siswa) {
            $data = $this->v($r, $siswa->id);
            if (empty($data['password'])) unset($data['password']);
            $siswa->update($data);
            $this->syncRombel($r, $siswa);
        });
        return redirect()->route('siswa.index')->with('success', 'Data siswa diperbarui.');
    }

    public function destroy(Siswa $siswa)
    {
        $siswa->delete();
        return back()->with('success', 'Data siswa dihapus.');
    }

    /* ===================== IMPORT / EXPORT ===================== */

    public function importForm()
    {
        return view('datacenter.siswa.import');
    }

    public function importStore(Request $r, SiswaExcelService $svc)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        $result = $svc->import($r->file('file'));

        return redirect()->route('siswa.import.form')
            ->with('success', "Import selesai: {$result->success} sukses, {$result->failed} gagal.")
            ->with('importErrors', $result->errors);
    }

    public function importTemplate(SiswaExcelService $svc)
    {
        return $svc->template();
    }

    public function exportExcel(Request $r, SiswaExcelService $svc)
    {
        $query = Siswa::with('rombelSekarang.rombel');
        if ($r->q) {
            $query->where(function ($q) use ($r) {
                $q->where('nama_siswa', 'like', "%{$r->q}%")
                  ->orWhere('nisn', 'like', "%{$r->q}%")
                  ->orWhere('nis', 'like', "%{$r->q}%");
            });
        }
        return $svc->export($query->orderBy('nama_siswa')->get());
    }

    protected function syncRombel(Request $r, Siswa $siswa): void
    {
        if ($rombelId = $r->input('rombongan_belajar_id')) {
            $rombel = RombonganBelajar::findOrFail($rombelId);
            SiswaRombel::updateOrCreate(
                ['siswa_id' => $siswa->id, 'tahun_ajaran_id' => $rombel->tahun_ajaran_id],
                ['rombongan_belajar_id' => $rombel->id]
            );
        }
    }

    protected function v(Request $r, $id = null): array
    {
        return $r->validate([
            'nisn' => 'required|string|max:20|unique:siswa,nisn,'.$id,
            'nis' => 'nullable|string|max:20',
            'nama_siswa' => 'required|string|max:255',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'agama' => 'nullable|string|max:30',
            'alamat' => 'nullable|string',
            'nomor_hp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'nama_ayah' => 'nullable|string|max:255',
            'nama_ibu' => 'nullable|string|max:255',
            'nomor_hp_ortu' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'is_aktif' => 'nullable|boolean',
        ]) + ['is_aktif' => $r->boolean('is_aktif', true)];
    }
}
