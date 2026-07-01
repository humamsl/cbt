<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use Illuminate\Support\Facades\Schema;

class LandingController extends Controller
{
    /**
     * Halaman portal publik (sebelum login). Menampilkan modul-modul yang
     * tersedia (Data Center & CBT) sebagai pintu masuk terpisah, tapi tetap
     * satu sistem akun yang terintegrasi.
     */
    public function index()
    {
        $stats = [
            'siswa' => Schema::hasTable('siswa') ? Siswa::count() : 0,
            'guru'  => Schema::hasTable('guru') ? Guru::count() : 0,
            'mapel' => Schema::hasTable('mata_pelajaran') ? MataPelajaran::count() : 0,
        ];

        return view('landing', compact('stats'));
    }
}
