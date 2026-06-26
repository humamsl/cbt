<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use Illuminate\Http\Request;

class SekolahController extends Controller
{
    public function edit()
    {
        $sekolah = Sekolah::first() ?? new Sekolah();
        return view('datacenter.sekolah.edit', compact('sekolah'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'npsn' => 'required|string|max:20',
            'nama_sekolah' => 'required|string|max:255',
            'jenjang' => 'required|string|max:20',
            'alamat' => 'nullable|string|max:255',
            'kelurahan' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'kabupaten' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'telepon' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'website' => 'nullable|string|max:100',
            'kepala_sekolah' => 'nullable|string|max:255',
            'nip_kepala_sekolah' => 'nullable|string|max:30',
        ]);

        $sekolah = Sekolah::first();
        if ($sekolah) {
            $sekolah->update($data);
        } else {
            Sekolah::create($data);
        }

        return back()->with('success', 'Profil sekolah berhasil disimpan.');
    }
}
