<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SchoolSeederBase;

/**
 * Seeder data dummy untuk SMP (Sekolah Menengah Pertama).
 * Tingkat 7, 8, 9 — tanpa jurusan, mapel umum.
 *
 * Jalankan: php artisan db:seed --class=SmpSeeder
 */
class SmpSeeder extends SchoolSeederBase
{
    protected function namaAplikasi(): string
    {
        return 'CBT SMP Negeri 1 Modern';
    }

    protected function sekolahProfile(): array
    {
        return [
            'npsn' => '20100001',
            'nama_sekolah' => 'SMP Negeri 1 Modern',
            'jenjang' => 'SMP',
            'alamat' => 'Jl. Pemuda No. 12',
            'kelurahan' => 'Sukajaya', 'kecamatan' => 'Bogor Utara',
            'kabupaten' => 'Bogor', 'provinsi' => 'Jawa Barat',
            'telepon' => '0251-1234567', 'email' => 'info@smpn1modern.sch.id',
            'website' => 'https://smpn1modern.sch.id',
            'kepala_sekolah' => 'Dra. Hj. Siti Nurhaliza, M.Pd.',
            'nip_kepala_sekolah' => '196801051992032001',
        ];
    }

    protected function tingkatRange(): array { return [7, 8, 9]; }

    // SMP tidak punya jurusan
    protected function jurusanList(): array { return []; }

    protected function mapelList(): array
    {
        return [
            // [kode, nama, kelompok, kode_jurusan, tingkat]
            ['MTK',  'Matematika',           'Umum', null, 7],
            ['BIN',  'Bahasa Indonesia',     'Umum', null, 7],
            ['BING', 'Bahasa Inggris',       'Umum', null, 7],
            ['IPA',  'Ilmu Pengetahuan Alam','Umum', null, 7],
            ['IPS',  'Ilmu Pengetahuan Sosial','Umum', null, 7],
            ['PKN',  'Pendidikan Pancasila & Kewarganegaraan','Umum', null, 7],
            ['AGM',  'Pendidikan Agama Islam','Umum', null, 7],
            ['PJK',  'Pendidikan Jasmani, Olahraga & Kesehatan','Umum', null, 7],
            ['SBK',  'Seni Budaya & Keterampilan','Umum', null, 7],
            ['PRA',  'Prakarya',             'Mulok', null, 7],
            ['IT',   'Informatika',          'Umum', null, 7],
        ];
    }

    protected function guruList(): array
    {
        return [
            // [nip, nama, jk, jabatan, status]
            ['198001011995032001', 'Andini Pratiwi, S.Pd.',          'P', 'Guru Matematika',         'PNS'],
            ['198205102001011002', 'Bambang Hartono, S.Pd.',         'L', 'Guru Bahasa Indonesia',   'PNS'],
            ['198607152008011003', 'Citra Dewi Lestari, M.Pd.',      'P', 'Guru Bahasa Inggris',     'PNS'],
            ['198912052015031004', 'Dimas Prasetyo, S.Si.',          'L', 'Guru IPA',                'PPPK'],
            ['199103082016032005', 'Eva Septiana, S.Sos.',           'P', 'Guru IPS',                'PPPK'],
            ['199506142019021006', 'Faisal Rahman, S.Pd.I.',         'L', 'Guru PAI',                'PPPK'],
            ['199207172020012007', 'Galuh Wulandari, S.Pd.',         'P', 'Guru PKn',                'GTT'],
            ['198506102021041008', 'Hendra Wijaya, S.Pd.',           'L', 'Guru PJOK',               'GTT'],
        ];
    }

    protected function rombelList(): array
    {
        // SMP: rombel format "7A", "7B", "8A", dst.
        $list = [];
        foreach ([7, 8, 9] as $t) {
            foreach (['A', 'B', 'C'] as $kelas) {
                $list[] = ["{$t}{$kelas}", $t, null];
            }
        }
        return $list;
    }

    protected function siswaList(): array
    {
        $nama = [
            ['Aisyah Nur Hasanah',  'P'], ['Bagas Setiawan',      'L'],
            ['Citra Maharani',      'P'], ['Dani Aditya',         'L'],
            ['Elsa Permata',        'P'], ['Farhan Mahesa',       'L'],
            ['Gita Kartika',        'P'], ['Hafidz Ramadhan',     'L'],
            ['Indira Sari',         'P'], ['Jihan Salsabila',     'P'],
            ['Khairul Amin',        'L'], ['Lia Anggraini',       'P'],
            ['Muhammad Rizki',      'L'], ['Nadia Putri',         'P'],
            ['Oka Pratama',         'L'], ['Putri Andini',        'P'],
            ['Qaisar Mahendra',     'L'], ['Rina Wulan',          'P'],
            ['Satria Bagus',        'L'], ['Tania Larasati',      'P'],
            ['Umar Bayu',           'L'], ['Vania Salim',         'P'],
            ['Wahyu Pratomo',       'L'], ['Xena Marsha',         'P'],
        ];

        $rombels = ['7A','7B','7C','8A','8B','8C','9A','9B','9C'];
        $list = [];
        foreach ($nama as $i => [$n, $jk]) {
            $list[] = [
                '010' . str_pad((string) ($i + 1), 9, '0', STR_PAD_LEFT), // nisn 12 dgt
                'SMP' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), // nis
                $n, $jk,
                $rombels[$i % count($rombels)],
            ];
        }
        return $list;
    }
}
