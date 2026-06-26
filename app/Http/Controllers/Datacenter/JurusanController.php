<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use Illuminate\Http\Request;

class JurusanController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->input('q');
        $items = Jurusan::when($q, fn ($x) => $x->where('nama_jurusan', 'like', "%$q%")->orWhere('kode_jurusan', 'like', "%$q%"))
            ->orderBy('nama_jurusan')
            ->paginate(15)->withQueryString();
        return view('datacenter.jurusan.index', compact('items', 'q'));
    }

    public function create() { return view('datacenter.jurusan.form', ['item' => new Jurusan()]); }

    public function store(Request $r)
    {
        Jurusan::create($this->v($r));
        return redirect()->route('jurusan.index')->with('success', 'Jurusan ditambahkan.');
    }

    public function edit(Jurusan $jurusan) { return view('datacenter.jurusan.form', ['item' => $jurusan]); }

    public function update(Request $r, Jurusan $jurusan)
    {
        $jurusan->update($this->v($r, $jurusan->id));
        return redirect()->route('jurusan.index')->with('success', 'Jurusan diperbarui.');
    }

    public function destroy(Jurusan $jurusan)
    {
        $jurusan->delete();
        return back()->with('success', 'Jurusan dihapus.');
    }

    protected function v(Request $r, $id = null): array
    {
        return $r->validate([
            'kode_jurusan' => 'required|string|max:20|unique:jurusan,kode_jurusan,'.$id,
            'nama_jurusan' => 'required|string|max:255',
            'singkatan' => 'nullable|string|max:10',
            'deskripsi' => 'nullable|string',
            'is_aktif' => 'nullable|boolean',
        ]) + ['is_aktif' => $r->boolean('is_aktif', true)];
    }
}
