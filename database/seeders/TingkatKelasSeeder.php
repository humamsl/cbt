<?php

namespace Database\Seeders;

use App\Models\TingkatKelas;
use Illuminate\Database\Seeder;

/**
 * Isi master Tingkat Kelas milik CBT (tabel `tingkat_kelas`).
 *
 * CBT membaca tingkat kelas dari tabelnya SENDIRI (dipakai dropdown di Bank
 * Soal & Registrasi Ujian) — TIDAK diambil dari Data Center. Jadi tingkat kelas
 * harus diisi lokal di CBT lewat seeder ini.
 *
 * AMAN dijalankan di produksi (idempotent, hanya menyentuh tabel tingkat_kelas):
 *     php artisan db:seed --class=TingkatKelasSeeder --force
 */
class TingkatKelasSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // [kode, nama, nomor, jenjang, urutan]
            ['1',  'Kelas 1',       1,  'SD',  1],
            ['2',  'Kelas 2',       2,  'SD',  2],
            ['3',  'Kelas 3',       3,  'SD',  3],
            ['4',  'Kelas 4',       4,  'SD',  4],
            ['5',  'Kelas 5',       5,  'SD',  5],
            ['6',  'Kelas 6',       6,  'SD',  6],
            ['7',  'Kelas 7',       7,  'SMP', 7],
            ['8',  'Kelas 8',       8,  'SMP', 8],
            ['9',  'Kelas 9',       9,  'SMP', 9],
            ['10', 'Kelas X (10)',  10, 'SMA', 10],
            ['11', 'Kelas XI (11)', 11, 'SMA', 11],
            ['12', 'Kelas XII (12)',12, 'SMA', 12],
        ];

        foreach ($data as [$kode, $nama, $nomor, $jenjang, $urutan]) {
            TingkatKelas::updateOrCreate(['kode' => $kode], [
                'nama' => $nama, 'nomor' => $nomor, 'jenjang' => $jenjang,
                'urutan' => $urutan, 'is_aktif' => true,
            ]);
        }
    }
}
