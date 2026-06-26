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
            'password' => 'required|string|min:6|confirmed',
        ]);
        $user = $r->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }
        $user->forceFill(['password' => $data['password']])->save();
        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
