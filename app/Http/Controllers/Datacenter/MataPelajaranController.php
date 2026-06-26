<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use Illuminate\Http\Request;

class MataPelajaranController extends Controller
{
    public function index(Request $r)
    {
        $items = MataPelajaran::with('jurusan')
            ->when($r->q, fn ($x) => $x->where('nama_mapel', 'like', "%{$r->q}%"))
            ->orderBy('tingkat')->orderBy('nama_mapel')
            ->paginate(20)->withQueryString();
        return view('datacenter.mapel.index', compact('items'));
    }

    public function create() { return view('datacenter.mapel.form', ['item' => new MataPelajaran(), 'jurusan' => Jurusan::orderBy('nama_jurusan')->get()]); }

    public function store(Request $r)
    {
        MataPelajaran::create($this->v($r));
        return redirect()->route('mapel.index')->with('success', 'Mata pelajaran ditambahkan.');
    }

    public function edit(MataPelajaran $mapel) { return view('datacenter.mapel.form', ['item' => $mapel, 'jurusan' => Jurusan::orderBy('nama_jurusan')->get()]); }

    public function update(Request $r, MataPelajaran $mapel)
    {
        $mapel->update($this->v($r, $mapel->id));
        return redirect()->route('mapel.index')->with('success', 'Mata pelajaran diperbarui.');
    }

    public function destroy(MataPelajaran $mapel)
    {
        $mapel->delete();
        return back()->with('success', 'Mata pelajaran dihapus.');
    }

    protected function v(Request $r, $id = null): array
    {
        return $r->validate([
            'kode_mapel' => 'required|string|max:20|unique:mata_pelajaran,kode_mapel,'.$id,
            'nama_mapel' => 'required|string|max:255',
            'kelompok' => 'nullable|string|max:50',
            'tingkat' => 'nullable|integer|between:1,12',
            'jurusan_id' => 'nullable|exists:jurusan,id',
            'deskripsi' => 'nullable|string',
            'is_aktif' => 'nullable|boolean',
        ]) + ['is_aktif' => $r->boolean('is_aktif', true)];
    }
}
