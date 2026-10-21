<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfilController extends Controller
{
    public function index(Request $r)
    {
        return view('profil.index', ['user' => $r->user()]);
    }

    public function updatePassword(Request $r)
    {
        $data = $r->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|confirmed|different:current_password|min:'.($r->user() instanceof \App\Models\User ? 8 : 6),
        ], ['password.different' => 'Password baru harus berbeda dari password lama.']);
        $user = $r->user();

        // Guru/siswa: model Guru/Siswa sudah terhubung langsung ke database
        // Data Center (koneksi mysql_datacenter, sama seperti Sekolah), jadi
        // cek & simpan password di sini otomatis langsung menulis ke baris asli
        // di Data Center — tidak ada HTTP call antar-app.
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password salah.']);
        }
        $attrs = ['password' => $data['password']];
        if ($user instanceof \App\Models\User) {
            $attrs['must_change_password'] = false;
        }
        $user->forceFill($attrs)->save();
        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
