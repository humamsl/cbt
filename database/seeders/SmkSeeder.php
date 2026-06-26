<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SchoolSeederBase;

/**
 * Seeder data dummy untuk SMK (Sekolah Menengah Kejuruan).
 * Tingkat 10, 11, 12 — jurusan kejuruan (RPL, TKJ, MM, AKL, OTKP, dst).
 *
 * Jalankan: php artisan db:seed --class=SmkSeeder
 */
class SmkSeeder extends SchoolSeederBase
{
    protected function namaAplikasi(): string
    {
        return 'CBT SMK Negeri 1 Modern';
    }

    protected function sekolahProfile(): array
    {
        return [
            'npsn' => '20300001',
            'nama_sekolah' => 'SMK Negeri 1 Modern',
            'jenjang' => 'SMK',
            'alamat' => 'Jl. Industri Raya No. 88',
            'kelurahan' => 'Pasir Putih', 'kecamatan' => 'Sawangan',
            'kabupaten' => 'Depok', 'provinsi' => 'Jawa Barat',
            'telepon' => '021-7777888', 'email' => 'info@smkn1modern.sch.id',
            'website' => 'https://smkn1modern.sch.id',
            'kepala_sekolah' => 'Drs. H. Imam Suryadi, M.M.',
            'nip_kepala_sekolah' => '196812121990031004',
        ];
    }

    protected function tingkatRange(): array { return [10, 11, 12]; }

    protected function jurusanList(): array
    {
        return [
            // [kode, nama, singkatan]
            ['RPL',  'Rekayasa Perangkat Lunak',              'RPL'],
            ['TKJ',  'Teknik Komputer & Jaringan',            'TKJ'],
            ['MM',   'Multimedia',                            'MM'],
            ['AKL',  'Akuntansi & Keuangan Lembaga',          'AKL'],
            ['OTKP', 'Otomatisasi & Tata Kelola Perkantoran', 'OTKP'],
            ['BDP',  'Bisnis Daring & Pemasaran',             'BDP'],
        ];
    }

    protected function mapelList(): array
    {
        return [
            // [kode, nama, kelompok, kode_jurusan, tingkat]
            // === Muatan Umum (semua jurusan)
            ['MTK',  'Matematika',           'Umum', null, 10],
            ['BIN',  'Bahasa Indonesia',     'Umum', null, 10],
            ['BING', 'Bahasa Inggris',       'Umum', null, 10],
            ['PKN',  'Pendidikan Pancasila', 'Umum', null, 10],
            ['AGM',  'Pendidikan Agama Islam','Umum', null, 10],
            ['SEJ',  'Sejarah Indonesia',    'Umum', null, 10],
            ['PJK',  'PJOK',                 'Umum', null, 10],
            ['SBD',  'Seni Budaya',          'Umum', null, 10],
            ['IPAS', 'IPA & IPS Terapan',    'Umum', null, 10],

            // === Dasar Kejuruan RPL
            ['DPRPL','Dasar Pemrograman',             'Kejuruan', 'RPL', 10],
            ['PBO',  'Pemrograman Berorientasi Objek','Kejuruan', 'RPL', 11],
            ['PWPB', 'Pemrograman Web & Perangkat Bergerak','Kejuruan', 'RPL', 11],
            ['BSD',  'Basis Data',                    'Kejuruan', 'RPL', 11],

            // === Dasar Kejuruan TKJ
            ['DKKJ', 'Dasar Komputer & Jaringan',     'Kejuruan', 'TKJ', 10],
            ['ASJ',  'Administrasi Sistem Jaringan',  'Kejuruan', 'TKJ', 11],
            ['TLJ',  'Teknologi Layanan Jaringan',    'Kejuruan', 'TKJ', 11],
            ['AIJ',  'Administrasi Infrastruktur Jaringan','Kejuruan', 'TKJ', 12],

            // === Dasar Kejuruan Multimedia
            ['DDG',  'Dasar Desain Grafis',           'Kejuruan', 'MM', 10],
            ['ANI',  'Animasi 2D & 3D',               'Kejuruan', 'MM', 11],
            ['PVE',  'Produksi Video & Editing',      'Kejuruan', 'MM', 12],

            // === Dasar Kejuruan AKL
            ['DAK',  'Dasar Akuntansi',               'Kejuruan', 'AKL', 10],
            ['AKJ',  'Akuntansi Keuangan',            'Kejuruan', 'AKL', 11],
            ['KOM',  'Komputer Akuntansi',            'Kejuruan', 'AKL', 12],

            // === Dasar Kejuruan OTKP
            ['DKO',  'Otomatisasi Perkantoran',       'Kejuruan', 'OTKP', 10],
            ['KORS', 'Korespondensi',                 'Kejuruan', 'OTKP', 11],

            // === Dasar Kejuruan BDP
            ['DBP',  'Dasar Bisnis & Pemasaran',      'Kejuruan', 'BDP', 10],
            ['MKT',  'Marketing Digital',             'Kejuruan', 'BDP', 11],
        ];
    }

    protected function guruList(): array
    {
        return [
            ['197005111995031001', 'Drs. Hartono Wijaya, M.T.',     'L', 'Wakasek Hubin',           'PNS'],
            ['197808152002032002', 'Ir. Indah Permatasari, M.Pd.',  'P', 'Kaprog RPL',              'PNS'],
            ['198106202005011003', 'Joko Suprapto, S.Kom., M.Kom.', 'L', 'Guru RPL',                'PNS'],
            ['198409102008012004', 'Kartika Sari, S.T.',            'P', 'Guru TKJ',                'PNS'],
            ['198712252010031005', 'Lukman Hakim, S.Kom.',          'L', 'Kaprog TKJ',              'PPPK'],
            ['198908152013022006', 'Maya Anggraeni, S.Ds.',         'P', 'Guru Multimedia',         'PPPK'],
            ['199102052015031007', 'Nurdin Saputra, S.E., M.Ak.',   'L', 'Kaprog AKL',              'PPPK'],
            ['199305182017042008', 'Olivia Damayanti, S.Pd.',       'P', 'Guru Bahasa Indonesia',   'GTT'],
            ['199506112019081009', 'Prabowo Kusuma, S.Pd.',         'L', 'Guru PJOK',               'GTT'],
            ['199708202020012010', 'Qurratul Aini, S.Pd.',          'P', 'Guru Matematika',         'GTT'],
            ['199810032021091011', 'Rizal Mahendra, S.E.',          'L', 'Guru BDP',                'GTT'],
            ['199912152022032012', 'Sari Wahyuni, S.Pd.',           'P', 'Guru OTKP',               'GTT'],
        ];
    }

    protected function rombelList(): array
    {
        // X RPL 1-2, X TKJ 1-2, X MM 1, X AKL 1, X OTKP 1, X BDP 1, dst untuk XI & XII
        $list = [];
        $tingkatLabel = [10 => 'X', 11 => 'XI', 12 => 'XII'];
        $jurusanRombel = [
            'RPL'  => 2,
            'TKJ'  => 2,
            'MM'   => 1,
            'AKL'  => 2,
            'OTKP' => 1,
            'BDP'  => 1,
        ];
        foreach ([10, 11, 12] as $t) {
            $label = $tingkatLabel[$t];
            foreach ($jurusanRombel as $kode => $jumlah) {
                for ($i = 1; $i <= $jumlah; $i++) {
                    $list[] = ["$label $kode $i", $t, $kode];
                }
            }
        }
        return $list;
    }

    protected function siswaList(): array
    {
        $nama = [
            ['Ahmad Rifai',         'L'], ['Bintang Aulia',       'P'],
            ['Candra Pratama',      'L'], ['Diana Citra',         'P'],
            ['Endra Saputra',       'L'], ['Farah Nabila',        'P'],
            ['Galang Maulidan',     'L'], ['Hesti Pertiwi',       'P'],
            ['Iqbal Maulana',       'L'], ['Jelita Rahmania',     'P'],
            ['Kresna Bayu',         'L'], ['Lily Andriani',       'P'],
            ['Mahesa Putra',        'L'], ['Nadya Alifia',        'P'],
            ['Oki Pranata',         'L'], ['Putri Sahara',        'P'],
            ['Qisya Marlina',       'P'], ['Reza Pahlevi',        'L'],
            ['Sinta Bella',         'P'], ['Teguh Pratama',       'L'],
            ['Untung Saputra',      'L'], ['Verani Astuti',       'P'],
            ['Wira Sentosa',        'L'], ['Yulia Anggraeni',     'P'],
            ['Zulfikar Ali',        'L'], ['Adelia Marsha',       'P'],
            ['Bilal Hidayat',       'L'], ['Clara Annisa',        'P'],
            ['Daffa Wiratama',      'L'], ['Erlina Saputri',      'P'],
            ['Fadhil Akbar',        'L'], ['Geyssa Putri',        'P'],
            ['Hafiz Maulana',       'L'], ['Inara Salsabila',     'P'],
            ['Jefri Setiawan',      'L'], ['Khalisa Putri',       'P'],
        ];

        $rombels = [
            'X RPL 1','X RPL 2','X TKJ 1','X TKJ 2','X MM 1','X AKL 1','X AKL 2','X OTKP 1','X BDP 1',
            'XI RPL 1','XI TKJ 1','XI MM 1','XII RPL 1','XII TKJ 1',
        ];
        $list = [];
        foreach ($nama as $i => [$n, $jk]) {
            $list[] = [
                '030' . str_pad((string) ($i + 1), 9, '0', STR_PAD_LEFT),
                'SMK' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                $n, $jk,
                $rombels[$i % count($rombels)],
            ];
        }
        return $list;
    }
}
