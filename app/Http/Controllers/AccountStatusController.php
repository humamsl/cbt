<?php

namespace App\Http\Controllers;

class AccountStatusController extends Controller
{
    public function suspended()
    {
        return response()->view('auth.account-status', [
            'tone'  => 'rose',
            'judul' => 'Akun Dibekukan (Suspended)',
            'pesan' => 'Akun Anda dibekukan sementara oleh administrator. Silakan hubungi admin sekolah.',
        ], 403);
    }

    public function locked()
    {
        $until = session('locked_until');
        return response()->view('auth.account-status', [
            'tone'  => 'amber',
            'judul' => 'Akun Terkunci Sementara',
            'pesan' => 'Akun Anda terkunci karena terlalu banyak percobaan login gagal'
                .($until ? '. Coba lagi setelah '.\Illuminate\Support\Carbon::parse($until)->translatedFormat('d M Y H:i') : '. Coba lagi dalam beberapa menit').'.',
        ], 423);
    }

    public function inactive()
    {
        return response()->view('auth.account-status', [
            'tone'  => 'sky',
            'judul' => 'Akun Belum Aktif',
            'pesan' => 'Akun Anda belum diaktifkan. Hubungi administrator sekolah untuk aktivasi.',
        ], 403);
    }
}
