<?php

namespace Database\Seeders\Concerns;

use App\Models\AppSetting;
use App\Models\Guru;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionType;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Role;
use App\Models\RombonganBelajar;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Base abstract untuk seeder per jenjang (SMP, SMA, SMK).
 * Mengandung helper umum yang sama untuk ketiga jenis sekolah.
 */
abstract class SchoolSeederBase extends Seeder
{
    /* ====================== HOOKS yang HARUS di-implement turunan ====================== */
    abstract protected function sekolahProfile(): array;
    abstract protected function namaAplikasi(): string;
    abstract protected function jurusanList(): array;       // [['IPA','Ilmu Pengetahuan Alam','IPA'], ...] — atau [] untuk SMP
    abstract protected function mapelList(): array;          // [['MTK','Matematika','Umum',null,tingkat], ...]
    abstract protected function guruList(): array;           // [['nip','nama','L|P','Jabatan','PNS'], ...]
    abstract protected function rombelList(): array;         // [['X IPA 1', 10, 'IPA' /*kode jurusan or null*/], ...]
    abstract protected function siswaList(): array;          // [['nisn','nis','nama','L|P','rombel_name'], ...]
    abstract protected function tingkatRange(): array;       // [10,11,12] / [7,8,9]

    /* ====================== RUN ====================== */
    public function run(): void
    {
        $this->command->info("Seeding {$this->namaAplikasi()}...");

        $this->seedAppSettings();
        $this->seedRolesAndPermissions();
        $this->seedAdminUser();
        $this->seedQuestionTypes();
        $this->seedTingkatKelas();

        $this->seedSekolah();
        $ta = $this->seedTahunAjaran();
        $jurusanMap = $this->seedJurusan();
        $mapelMap = $this->seedMapel($jurusanMap);
        $guruMap = $this->seedGuru();
        $rombelMap = $this->seedRombel($ta, $jurusanMap, $guruMap);
        $this->seedSiswa($ta, $rombelMap);

        $this->seedContohSoalDanQuiz($mapelMap, $guruMap, $rombelMap, $ta);

        $this->command->info("✓ Selesai. Sekolah: {$this->sekolahProfile()['nama_sekolah']}");
    }

    /* ====================== SEEDERS (shared) ====================== */
    protected function seedAppSettings(): void
    {
        $defaults = [
            ['app_name',       $this->namaAplikasi(),   'text', 'aplikasi'],
            ['app_tagline',    'Sistem Informasi Sekolah Terintegrasi', 'text', 'aplikasi'],
            ['theme_color',    '#1f47f5', 'color', 'tampilan'],
            ['login_title',    'Selamat datang di platform <span class="text-amber-300">CBT Modern</span> '.$this->sekolahProfile()['nama_sekolah'].'.', 'text', 'login'],
            ['login_subtitle', 'Kelola data guru, siswa, kelas, dan ujian online dalam satu dashboard yang cepat, aman, dan mudah digunakan.', 'text', 'login'],
            ['footer_text',    '© '.date('Y').' '.$this->sekolahProfile()['nama_sekolah'].'. Hak cipta dilindungi.', 'text', 'aplikasi'],
        ];
        foreach ($defaults as [$k, $v, $type, $group]) {
            AppSetting::updateOrCreate(['key' => $k], ['value' => $v, 'type' => $type, 'group' => $group]);
        }
    }

    protected function seedRolesAndPermissions(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin'], ['label' => 'Super Administrator', 'is_system' => true]);
        $admin      = Role::firstOrCreate(['name' => 'admin'],       ['label' => 'Administrator',       'is_system' => true]);
        $operator   = Role::firstOrCreate(['name' => 'operator'],    ['label' => 'Operator Data',       'is_system' => false]);

        $list = [
            ['dashboard/index', 'Lihat Dashboard', 'umum'],
            ['profil/index',    'Lihat Profil',     'umum'],
            ['profil/password', 'Ubah Password',    'umum'],
            ['sekolah/edit',    'Edit Profil Sekolah','datacenter'],
            ['tahun-ajaran/*',  'Kelola Tahun Ajaran','datacenter'],
            ['jurusan/*',       'Kelola Jurusan',     'datacenter'],
            ['mapel/*',         'Kelola Mapel',       'datacenter'],
            ['rombel/*',        'Kelola Rombel',      'datacenter'],
            ['guru/*',          'Kelola Guru',        'datacenter'],
            ['siswa/*',         'Kelola Siswa',       'datacenter'],
            ['topik/*',         'Kelola Topik',       'cbt'],
            ['bank-soal/*',     'Kelola Bank Soal',   'cbt'],
            ['tes/*',           'Kelola Tes',         'cbt'],
            ['token-sesi/*',    'Kelola Token Sesi',  'cbt'],
            ['hasil/*',         'Hasil, Statistik, Analisis & Export', 'cbt'],
            ['setting/index',   'Lihat Pengaturan',   'admin'],
            ['setting/update',  'Ubah Pengaturan',    'admin'],
            ['backup/*',        'Backup & Restore',   'admin'],
            ['monitoring/index','Monitoring Ujian',   'admin'],
            ['monitoring/block','Blokir Ujian',       'admin'],
            ['monitoring/unblock','Buka Blokir',      'admin'],
            ['monitoring/reset','Reset Attempt',      'admin'],
            ['monitoring/lihat','Lihat Detail',       'admin'],
            ['log-login/index', 'Log Login',          'admin'],
            ['tingkat-kelas/*', 'Kelola Tingkat Kelas','datacenter'],
            ['guru-mapel/*',    'Data Guru Mapel','datacenter'],
        ];
        $allIds = [];
        $opIds = [];
        foreach ($list as [$perm, $label, $group]) {
            $p = Permission::firstOrCreate(['permission' => $perm], ['label' => $label, 'group' => $group]);
            $allIds[] = $p->id;
            if (in_array($group, ['umum', 'datacenter']) && !in_array($perm, ['sekolah/edit', 'guru/*'])) {
                $opIds[] = $p->id;
            }
        }
        $superAdmin->permissions()->sync($allIds);
        $admin->permissions()->sync($allIds);
        $operator->permissions()->sync($opIds);
    }

    protected function seedAdminUser(): void
    {
        $superAdmin = Role::where('name', 'super-admin')->first();
        User::updateOrCreate(
            ['email' => 'admin@sekolah.test'],
            [
                'name'           => 'Administrator',
                'password'       => Hash::make('password'),
                'role'           => 'admin',
                'role_id'        => $superAdmin?->id,
                'account_status' => 'active',
                'is_aktif'       => true,
            ]
        );
    }

    protected function seedQuestionTypes(): void
    {
        $allowed = [
            'pg'          => 'Pilihan Ganda',
            'pgk'         => 'Pilihan Ganda Kompleks',
            'fill-blank'  => 'Fill the Blank',
            'penjodohan'  => 'Penjodohan',
            'benar-salah' => 'Benar / Salah',
        ];
        foreach ($allowed as $slug => $name) {
            QuestionType::updateOrCreate(['slug' => $slug], ['question_type' => $name]);
        }
        QuestionType::whereNotIn('slug', array_keys($allowed))->delete();
    }

    protected function seedTingkatKelas(): void
    {
        $data = [
            // [kode, nama, nomor, jenjang, urutan]
            ['1',  'Kelas 1',  1,  'SD',  1],
            ['2',  'Kelas 2',  2,  'SD',  2],
            ['3',  'Kelas 3',  3,  'SD',  3],
            ['4',  'Kelas 4',  4,  'SD',  4],
            ['5',  'Kelas 5',  5,  'SD',  5],
            ['6',  'Kelas 6',  6,  'SD',  6],
            ['7',  'Kelas 7',  7,  'SMP', 7],
            ['8',  'Kelas 8',  8,  'SMP', 8],
            ['9',  'Kelas 9',  9,  'SMP', 9],
            ['10', 'Kelas X (10)',  10, 'SMA', 10],
            ['11', 'Kelas XI (11)', 11, 'SMA', 11],
            ['12', 'Kelas XII (12)',12, 'SMA', 12],
        ];
        foreach ($data as [$kode, $nama, $nomor, $jenjang, $urutan]) {
            \App\Models\TingkatKelas::updateOrCreate(['kode' => $kode], [
                'nama' => $nama, 'nomor' => $nomor, 'jenjang' => $jenjang, 'urutan' => $urutan, 'is_aktif' => true,
            ]);
        }
    }

    protected function seedSekolah(): void
    {
        $p = $this->sekolahProfile();
        Sekolah::updateOrCreate(['npsn' => $p['npsn']], $p);
    }

    protected function seedTahunAjaran(): TahunAjaran
    {
        return TahunAjaran::updateOrCreate(
            ['kode_tahun_ajaran' => '2526'],
            [
                'nama_tahun_ajaran' => '2025/2026',
                'semester' => 'Ganjil',
                'is_aktif' => true,
                'tanggal_mulai' => now()->setDate(2025, 7, 15),
                'tanggal_selesai' => now()->setDate(2026, 6, 30),
            ]
        );
    }

    /** Returns: ['IPA' => Jurusan, ...] */
    protected function seedJurusan(): array
    {
        $map = [];
        foreach ($this->jurusanList() as [$kode, $nama, $singkatan]) {
            $map[$kode] = Jurusan::firstOrCreate(['kode_jurusan' => $kode], [
                'nama_jurusan' => $nama, 'singkatan' => $singkatan, 'is_aktif' => true,
            ]);
        }
        return $map;
    }

    /** Returns: ['MTK' => MataPelajaran, ...] */
    protected function seedMapel(array $jurusanMap): array
    {
        $map = [];
        foreach ($this->mapelList() as [$kode, $nama, $kelompok, $kodeJurusan, $tingkat]) {
            $map[$kode] = MataPelajaran::firstOrCreate(['kode_mapel' => $kode], [
                'nama_mapel' => $nama,
                'kelompok'   => $kelompok,
                'tingkat'    => $tingkat,
                'jurusan_id' => $kodeJurusan ? ($jurusanMap[$kodeJurusan]->id ?? null) : null,
                'is_aktif'   => true,
            ]);
        }
        return $map;
    }

    /** Returns: ['nip' => Guru, ...] */
    protected function seedGuru(): array
    {
        $map = [];
        foreach ($this->guruList() as [$nip, $nama, $jk, $jabatan, $status]) {
            $map[$nip] = Guru::updateOrCreate(['nip' => $nip], [
                'nama_ptk' => $nama,
                'email'    => strtolower(str_replace(' ', '.', $nama)).'@sekolah.test',
                'jenis_kelamin' => $jk,
                'jabatan' => $jabatan,
                'status_kepegawaian' => $status,
                'password' => Hash::make('password'),
                'is_aktif' => true,
                'account_status' => 'active',
            ]);
        }
        return $map;
    }

    /** Returns: ['X IPA 1' => RombonganBelajar, ...] */
    protected function seedRombel(TahunAjaran $ta, array $jurusanMap, array $guruMap): array
    {
        $map = [];
        $guruArr = array_values($guruMap);
        $i = 0;
        foreach ($this->rombelList() as [$nama, $tingkat, $kodeJurusan]) {
            $wali = $guruArr[$i % count($guruArr)] ?? null;
            $map[$nama] = RombonganBelajar::firstOrCreate(
                ['nama_rombel' => $nama, 'tahun_ajaran_id' => $ta->id],
                [
                    'tingkat' => $tingkat,
                    'jurusan_id' => $kodeJurusan ? ($jurusanMap[$kodeJurusan]->id ?? null) : null,
                    'wali_kelas_id' => $wali?->id,
                    'kapasitas' => 36,
                ]
            );
            $i++;
        }
        return $map;
    }

    protected function seedSiswa(TahunAjaran $ta, array $rombelMap): void
    {
        foreach ($this->siswaList() as [$nisn, $nis, $nama, $jk, $rombelName]) {
            $s = Siswa::updateOrCreate(['nisn' => $nisn], [
                'nis'        => $nis,
                'nama_siswa' => $nama,
                'jenis_kelamin' => $jk,
                'agama'      => 'Islam',
                'password'   => Hash::make('password'),
                'is_aktif'   => true,
                'account_status' => 'active',
            ]);
            if (isset($rombelMap[$rombelName])) {
                SiswaRombel::updateOrCreate(
                    ['siswa_id' => $s->id, 'tahun_ajaran_id' => $ta->id],
                    ['rombongan_belajar_id' => $rombelMap[$rombelName]->id]
                );
            }
        }
    }

    protected function seedContohSoalDanQuiz(array $mapelMap, array $guruMap, array $rombelMap, TahunAjaran $ta): void
    {
        $mtk = $mapelMap['MTK'] ?? array_values($mapelMap)[0] ?? null;
        if (! $mtk) return;

        $guru = array_values($guruMap)[0];
        $rombel = array_values($rombelMap)[0];
        $pg = QuestionType::where('slug', 'pg')->first();

        $bank = [
            ['Penjumlahan sederhana', 'Berapakah hasil dari 25 + 17?', ['32', '42', '52', '62'], 1],
            ['Perkalian dasar',       'Berapakah hasil dari 12 × 8?',  ['86', '88', '96', '108'], 2],
            ['Akar kuadrat',          'Akar kuadrat dari 144 adalah?', ['10', '11', '12', '13'], 2],
            ['Persamaan linear',      'Jika 3x + 5 = 20, maka x = ?',  ['3', '4', '5', '6'], 2],
            ['Bangun datar',          'Luas persegi sisi 8 cm adalah?',['16', '32', '48', '64'], 3],
        ];

        $created = [];
        foreach ($bank as $row) {
            $q = Question::firstOrCreate(
                ['title' => $row[0], 'mata_pelajaran_id' => $mtk->id],
                [
                    'question' => $row[1],
                    'question_type_id' => $pg?->id,
                    'tingkat' => $this->tingkatRange()[0] ?? 10,
                    'tingkat_kesulitan' => 'sedang',
                    'created_by_guru_id' => $guru->id,
                ]
            );
            $q->options()->delete();
            foreach ($row[2] as $i => $opt) {
                QuestionOption::create([
                    'question_id' => $q->id, 'option_text' => $opt,
                    'is_correct' => $i === $row[3], 'order' => $i,
                ]);
            }
            $created[] = $q;
        }

        $quiz = Quiz::firstOrCreate(['slug' => 'uts-mtk-'.strtolower(class_basename(static::class))], [
            'name' => 'UTS Matematika',
            'description' => 'Ujian tengah semester matematika.',
            'mata_pelajaran_id' => $mtk->id,
            'rombongan_belajar_id' => $rombel->id,
            'tahun_ajaran_id' => $ta->id,
            'created_by_guru_id' => $guru->id,
            'tingkat' => $this->tingkatRange()[0] ?? 10,
            'duration' => 30,
            'pass_marks' => 60,
            'max_attempts' => 1,
            'is_published' => true,
            'show_score' => true,
            'protection_enabled' => true,
            'max_violations' => 5,
            'valid_from' => now()->subDay(),
            'valid_upto' => now()->addDays(30),
        ]);
        foreach ($created as $idx => $q) {
            QuizQuestion::firstOrCreate(
                ['quiz_id' => $quiz->id, 'question_id' => $q->id],
                ['marks' => 20, 'order' => $idx + 1]
            );
        }
        $quiz->update(['total_marks' => $quiz->questions()->sum('marks')]);
    }
}
