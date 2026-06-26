<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SchoolSeederBase;

/**
 * Seeder data dummy untuk SMA (Sekolah Menengah Atas).
 * Tingkat 10, 11, 12 — jurusan IPA / IPS / Bahasa.
 *
 * Jalankan: php artisan db:seed --class=SmaSeeder
 */
class SmaSeeder extends SchoolSeederBase
{
    protected function namaAplikasi(): string
    {
        return 'CBT SMA Negeri 1 Modern';
    }

    protected function sekolahProfile(): array
    {
        return [
            'npsn' => '20200001',
            'nama_sekolah' => 'SMA Negeri 1 Modern',
            'jenjang' => 'SMA',
            'alamat' => 'Jl. Diponegoro No. 24',
            'kelurahan' => 'Tegalega', 'kecamatan' => 'Kota Bogor',
            'kabupaten' => 'Kota Bogor', 'provinsi' => 'Jawa Barat',
            'telepon' => '0251-2345678', 'email' => 'info@sman1modern.sch.id',
            'website' => 'https://sman1modern.sch.id',
            'kepala_sekolah' => 'Dr. H. Bambang Sutrisno, M.Pd.',
            'nip_kepala_sekolah' => '197005101995031003',
        ];
    }

    protected function tingkatRange(): array { return [10, 11, 12]; }

    protected function jurusanList(): array
    {
        return [
            // [kode, nama, singkatan]
            ['IPA',  'Matematika & Ilmu Pengetahuan Alam', 'MIPA'],
            ['IPS',  'Ilmu Pengetahuan Sosial',            'IPS'],
            ['BHS',  'Bahasa & Budaya',                    'BB'],
        ];
    }

    protected function mapelList(): array
    {
        return [
            // [kode, nama, kelompok, kode_jurusan, tingkat]
            // Mapel Umum (semua jurusan)
            ['MTK',  'Matematika',           'Umum', null, 10],
            ['BIN',  'Bahasa Indonesia',     'Umum', null, 10],
            ['BING', 'Bahasa Inggris',       'Umum', null, 10],
            ['PKN',  'Pendidikan Pancasila & Kewarganegaraan','Umum', null, 10],
            ['AGM',  'Pendidikan Agama Islam','Umum', null, 10],
            ['SEJ',  'Sejarah Indonesia',    'Umum', null, 10],
            ['PJK',  'PJOK',                 'Umum', null, 10],
            ['SBD',  'Seni Budaya',          'Umum', null, 10],
            // Peminatan IPA
            ['FIS',  'Fisika',               'Peminatan', 'IPA', 10],
            ['KIM',  'Kimia',                'Peminatan', 'IPA', 10],
            ['BIO',  'Biologi',              'Peminatan', 'IPA', 10],
            ['MTM',  'Matematika Peminatan', 'Peminatan', 'IPA', 10],
            // Peminatan IPS
            ['EKO',  'Ekonomi',              'Peminatan', 'IPS', 10],
            ['GEO',  'Geografi',             'Peminatan', 'IPS', 10],
            ['SOS',  'Sosiologi',            'Peminatan', 'IPS', 10],
            ['SEJP', 'Sejarah Peminatan',    'Peminatan', 'IPS', 10],
            // Peminatan Bahasa
            ['BIND', 'Bahasa Indonesia Peminatan', 'Peminatan', 'BHS', 10],
            ['BINGP','Bahasa Inggris Peminatan',   'Peminatan', 'BHS', 10],
            ['BSAR', 'Bahasa Arab',          'Peminatan', 'BHS', 10],
            ['ANT',  'Antropologi',          'Peminatan', 'BHS', 10],
        ];
    }

    protected function guruList(): array
    {
        return [
            ['197505101998031001', 'Drs. Agus Salim, M.Pd.',         'L', 'Wakasek Kurikulum',       'PNS'],
            ['198008052001031002', 'Diana Pertiwi, S.Pd., M.Pd.',    'P', 'Guru Matematika',         'PNS'],
            ['198202152005012003', 'Eko Widodo, S.Si., M.Si.',       'L', 'Guru Fisika',             'PNS'],
            ['198512102008012004', 'Fitri Handayani, S.Pd.',         'P', 'Guru Bahasa Inggris',     'PNS'],
            ['198808202010031005', 'Gunawan Hidayat, M.Pd.',         'L', 'Guru Kimia',              'PPPK'],
            ['199103082013022006', 'Hesti Rahayu, S.Sos., M.Pd.',    'P', 'Guru Sosiologi',          'PPPK'],
            ['199205162015011007', 'Irfan Maulana, S.Pd.',           'L', 'Guru PJOK',               'PPPK'],
            ['199403182017042008', 'Jihan Kartika, S.Pd.',           'P', 'Guru Biologi',            'GTT'],
            ['199607252019081009', 'Kemal Pasha, S.Pd.',             'L', 'Guru Sejarah',            'GTT'],
            ['199809102021091010', 'Lestari Wulandari, S.Pd.',       'P', 'Guru Bahasa Indonesia',   'GTT'],
        ];
    }

    protected function rombelList(): array
    {
        // X IPA 1-3, X IPS 1-2, X BHS 1, lalu tingkat 11 & 12 sama
        $list = [];
        $tingkatLabel = [10 => 'X', 11 => 'XI', 12 => 'XII'];
        foreach ([10, 11, 12] as $t) {
            $label = $tingkatLabel[$t];
            for ($i = 1; $i <= 3; $i++) $list[] = ["$label IPA $i", $t, 'IPA'];
            for ($i = 1; $i <= 2; $i++) $list[] = ["$label IPS $i", $t, 'IPS'];
            $list[] = ["$label BHS 1", $t, 'BHS'];
        }
        return $list;
    }

    protected function siswaList(): array
    {
        $nama = [
            ['Aditya Wirawan',      'L'], ['Bella Anggraeni',     'P'],
            ['Cahyo Nugroho',       'L'], ['Dewi Sartika',        'P'],
            ['Erlangga Putra',      'L'], ['Fitriana Devi',       'P'],
            ['Gilang Ramadhan',     'L'], ['Hanifah Zahra',       'P'],
            ['Ilham Akbar',         'L'], ['Jasmine Aurelia',     'P'],
            ['Kevin Sanjaya',       'L'], ['Lina Marpaung',       'P'],
            ['Maulana Yusuf',       'L'], ['Nayla Ramadhani',     'P'],
            ['Oktavian Hakim',      'L'], ['Putri Maharani',      'P'],
            ['Qori Saputra',        'L'], ['Rahma Aulia',         'P'],
            ['Surya Pratama',       'L'], ['Tiara Larasati',      'P'],
            ['Usman Ramadhan',      'L'], ['Vina Septiana',       'P'],
            ['Wahyu Nugraha',       'L'], ['Yasmin Khairunnisa',  'P'],
            ['Zaki Maulana',        'L'], ['Anggi Saputri',       'P'],
            ['Bayu Wicaksono',      'L'], ['Cintya Bella',        'P'],
            ['Doni Wibowo',         'L'], ['Erika Pranata',       'P'],
        ];

        $rombels = [
            'X IPA 1','X IPA 2','X IPA 3','X IPS 1','X IPS 2','X BHS 1',
            'XI IPA 1','XI IPS 1','XII IPA 1','XII IPS 1',
        ];
        $list = [];
        foreach ($nama as $i => [$n, $jk]) {
            $list[] = [
                '020' . str_pad((string) ($i + 1), 9, '0', STR_PAD_LEFT),
                'SMA' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                $n, $jk,
                $rombels[$i % count($rombels)],
            ];
        }
        return $list;
    }
}
