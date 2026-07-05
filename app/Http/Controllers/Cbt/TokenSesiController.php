<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\SessionToken;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TokenSesiController extends Controller
{
    public function index()
    {
        $items = SessionToken::with('tahunAjaran')->latest()->paginate(15);
        return view('cbt.token-sesi.index', compact('items'));
    }

    public function create()
    {
        return view('cbt.token-sesi.form', [
            'item' => new SessionToken(['token' => strtoupper(Str::random(6))]),
            'tahunAjaran' => TahunAjaran::orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $r)
    {
        SessionToken::create($this->v($r));
        return redirect()->route('token-sesi.index')->with('success', 'Token sesi dibuat.');
    }

    public function edit(SessionToken $tokenSesi)
    {
        return view('cbt.token-sesi.form', [
            'item' => $tokenSesi,
            'tahunAjaran' => TahunAjaran::orderByDesc('id')->get(),
        ]);
    }

    public function update(Request $r, SessionToken $tokenSesi)
    {
        $tokenSesi->update($this->v($r, $tokenSesi->id));
        return redirect()->route('token-sesi.index')->with('success', 'Token diperbarui.');
    }

    public function destroy(SessionToken $tokenSesi)
    {
        $tokenSesi->delete();
        return back()->with('success', 'Token dihapus.');
    }

    protected function v(Request $r, $id = null): array
    {
        return $r->validate([
            'token' => 'required|string|max:12|unique:session_tokens,token,'.$id,
            'nama_sesi' => 'nullable|string|max:100',
            'tahun_ajaran_id' => 'nullable|exists:mysql_datacenter.tahun_ajaran,id',
            'valid_from' => 'nullable|date',
            'valid_upto' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'nullable|boolean',
        ]) + ['is_active' => $r->boolean('is_active', true)];
    }
}
