<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TahunAjaranController extends Controller
{
    public function index()
    {
        $items = TahunAjaran::orderByDesc('id')->paginate(15);
        return view('datacenter.tahun-ajaran.index', compact('items'));
    }

    public function create()
    {
        return view('datacenter.tahun-ajaran.form', ['item' => new TahunAjaran()]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        TahunAjaran::create($data);
        $this->syncAktif($data);
        return redirect()->route('tahun-ajaran.index')->with('success', 'Tahun ajaran ditambahkan.');
    }

    public function edit(TahunAjaran $tahunAjaran)
    {
        return view('datacenter.tahun-ajaran.form', ['item' => $tahunAjaran]);
    }

    public function update(Request $request, TahunAjaran $tahunAjaran)
    {
        $data = $this->validateData($request, $tahunAjaran->id);
        $tahunAjaran->update($data);
        $this->syncAktif($data);
        return redirect()->route('tahun-ajaran.index')->with('success', 'Tahun ajaran diperbarui.');
    }

    public function destroy(TahunAjaran $tahunAjaran)
    {
        $tahunAjaran->delete();
        return back()->with('success', 'Tahun ajaran dihapus.');
    }

    protected function validateData(Request $r, $id = null): array
    {
        return $r->validate([
            'kode_tahun_ajaran' => 'required|string|max:10|unique:tahun_ajaran,kode_tahun_ajaran,'.$id,
            'nama_tahun_ajaran' => 'required|string|max:30',
            'semester' => 'required|in:Ganjil,Genap',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date',
            'is_aktif' => 'nullable|boolean',
        ]) + ['is_aktif' => $r->boolean('is_aktif')];
    }

    protected function syncAktif(array $data): void
    {
        if (! empty($data['is_aktif'])) {
            DB::table('tahun_ajaran')->where('kode_tahun_ajaran', '!=', $data['kode_tahun_ajaran'])
                ->update(['is_aktif' => false]);
        }
    }
}
