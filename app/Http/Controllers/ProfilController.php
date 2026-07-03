<?php

namespace App\Http\Controllers;

use App\Services\DatacenterClient;
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
            'password' => 'required|string|min:6|confirmed',
        ]);
        $user = $r->user();

        // Guru/siswa: password hanya pernah disimpan di Data Center — proksikan
        // ganti password ke sana, bukan bandingkan/hash lokal (lihat DatacenterClient).
        if (in_array($user->user_type, ['guru', 'siswa'], true)) {
            $client = app(DatacenterClient::class);
            $response = $user->user_type === 'guru'
                ? $client->changePasswordGuru($user->nip, $data['current_password'], $data['password'])
                : $client->changePasswordSiswa($user->nisn, $data['current_password'], $data['password']);

            if (! $response->successful()) {
                return back()->withErrors(['current_password' => $response->json('message', 'Password saat ini salah.')]);
            }

            return back()->with('success', 'Password berhasil diperbarui.');
        }

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }
        $user->forceFill(['password' => $data['password']])->save();
        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
