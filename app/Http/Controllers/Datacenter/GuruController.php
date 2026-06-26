<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Services\Master\GuruExcelService;
use Illuminate\Http\Request;

class GuruController extends Controller
{
    public function index(Request $r)
    {
        $items = Guru::when($r->q, function ($x) use ($r) {
                $x->where('nama_ptk', 'like', "%{$r->q}%")
                  ->orWhere('nip', 'like', "%{$r->q}%");
            })->orderBy('nama_ptk')->paginate(20)->withQueryString();
        return view('datacenter.guru.index', compact('items'));
    }

    public function create() { return view('datacenter.guru.form', ['item' => new Guru()]); }

    public function store(Request $r)
    {
        $data = $this->v($r);
        $data['password'] = $data['password'] ?? 'password'; // password default untuk guru baru
        Guru::create($data);
        return redirect()->route('guru.index')->with('success', 'Data guru ditambahkan.');
    }

    public function edit(Guru $guru) { return view('datacenter.guru.form', ['item' => $guru]); }

    public function update(Request $r, Guru $guru)
    {
        $data = $this->v($r, $guru->id);
        if (empty($data['password'])) unset($data['password']);
        $guru->update($data);
        return redirect()->route('guru.index')->with('success', 'Data guru diperbarui.');
    }

    public function destroy(Guru $guru)
    {
        $guru->delete();
        return back()->with('success', 'Data guru dihapus.');
    }

    /* ===================== IMPORT / EXPORT ===================== */

    public function importForm()
    {
        return view('datacenter.guru.import');
    }

    public function importStore(Request $r, GuruExcelService $svc)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);
        $result = $svc->import($r->file('file'));

        return redirect()->route('guru.import.form')
            ->with('success', "Import selesai: {$result->success} sukses, {$result->failed} gagal.")
            ->with('importErrors', $result->errors);
    }

    public function importTemplate(GuruExcelService $svc)
    {
        return $svc->template();
    }

    public function exportExcel(Request $r, GuruExcelService $svc)
    {
        $query = Guru::query();
        if ($r->q) {
            $query->where('nama_ptk', 'like', "%{$r->q}%")
                  ->orWhere('nip', 'like', "%{$r->q}%");
        }
        return $svc->export($query->orderBy('nama_ptk')->get());
    }

    protected function v(Request $r, $id = null): array
    {
        return $r->validate([
            'nip' => 'required|string|max:30|unique:guru,nip,'.$id,
            'nama_ptk' => 'required|string|max:255',
            'email' => 'nullable|email|max:100',
            'nomor_hp' => 'nullable|string|max:20',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:100',
            'status_kepegawaian' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6',
            'is_aktif' => 'nullable|boolean',
        ]) + ['is_aktif' => $r->boolean('is_aktif', true)];
    }
}
