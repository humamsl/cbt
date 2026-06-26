<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\TingkatKelas;
use Illuminate\Http\Request;

class TingkatKelasController extends Controller
{
    public function index()
    {
        $items = TingkatKelas::orderBy('urutan')->orderBy('nomor')->paginate(20);
        return view('datacenter.tingkat-kelas.index', compact('items'));
    }

    public function create() { return view('datacenter.tingkat-kelas.form', ['item' => new TingkatKelas()]); }

    public function store(Request $r)
    {
        TingkatKelas::create($this->v($r));
        return redirect()->route('tingkat-kelas.index')->with('success', 'Tingkat kelas ditambahkan.');
    }

    public function edit(TingkatKelas $tingkatKelas) { return view('datacenter.tingkat-kelas.form', ['item' => $tingkatKelas]); }

    public function update(Request $r, TingkatKelas $tingkatKelas)
    {
        $tingkatKelas->update($this->v($r, $tingkatKelas->id));
        return redirect()->route('tingkat-kelas.index')->with('success', 'Tingkat kelas diperbarui.');
    }

    public function destroy(TingkatKelas $tingkatKelas)
    {
        $tingkatKelas->delete();
        return back()->with('success', 'Tingkat kelas dihapus.');
    }

    protected function v(Request $r, $id = null): array
    {
        return $r->validate([
            'kode' => 'required|string|max:10|unique:tingkat_kelas,kode,'.$id,
            'nama' => 'required|string|max:50',
            'nomor' => 'required|integer|between:1,12',
            'jenjang' => 'nullable|string|max:10',
            'urutan' => 'nullable|integer',
            'is_aktif' => 'nullable|boolean',
        ]) + ['is_aktif' => $r->boolean('is_aktif', true), 'urutan' => $r->input('urutan', 0)];
    }
}
