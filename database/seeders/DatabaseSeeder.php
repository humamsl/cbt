<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Guru;
use App\Models\Jurusan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionType;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\RombonganBelajar;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- AppSettings default ---
        $defaults = [
            ['app_name',       'CBT Sekolah',        'text', 'aplikasi'],
            ['app_tagline',    'Sistem Informasi Sekolah Terintegrasi', 'text', 'aplikasi'],
            ['theme_color',    '#1f47f5',            'color','tampilan'],
            ['login_title',    'Selamat datang di platform <span class="text-amber-300">CBT Modern</span> sekolah Anda.', 'text', 'login'],
            ['login_subtitle', 'Kelola data guru, siswa, kelas, dan ujian online dalam satu dashboard yang cepat, aman, dan mudah digunakan.', 'text', 'login'],
            ['footer_text',    null, 'text', 'aplikasi'],
        ];
        foreach ($defaults as [$key, $val, $type, $group]) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => $val, 'type' => $type, 'group' => $group]);
        }

        // --- Roles & Permissions ---
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin'],
            ['label' => 'Super Administrator', 'is_system' => true]);
        $admin      = Role::firstOrCreate(['name' => 'admin'],
            ['label' => 'Administrator', 'is_system' => true]);
        $operator   = Role::firstOrCreate(['name' => 'operator'],
            ['label' => 'Operator Data', 'is_system' => false]);

        $permList = [
            ['dashboard/index', 'Lihat Dashboard', 'umum'],
            ['profil/index', 'Lihat Profil', 'umum'],
            ['profil/password', 'Ubah Password', 'umum'],
            ['sekolah/edit', 'Edit Profil Sekolah', 'datacenter'],
            ['tahun-ajaran/*', 'Kelola Tahun Ajaran', 'datacenter'],
            ['jurusan/*', 'Kelola Jurusan', 'datacenter'],
            ['mapel/*', 'Kelola Mapel', 'datacenter'],
            ['rombel/*', 'Kelola Rombel', 'datacenter'],
            ['guru/*', 'Kelola Guru', 'datacenter'],
            ['siswa/*', 'Kelola Siswa', 'datacenter'],
            ['topik/*', 'Kelola Topik', 'cbt'],
            ['bank-soal/*', 'Kelola Bank Soal', 'cbt'],
            ['tes/*', 'Kelola Tes', 'cbt'],
            ['token-sesi/*', 'Kelola Token Sesi', 'cbt'],
            ['hasil/index', 'Lihat Hasil', 'cbt'],
            ['hasil/detail', 'Detail Hasil', 'cbt'],
            ['setting/index', 'Lihat Pengaturan', 'admin'],
            ['setting/update', 'Ubah Pengaturan', 'admin'],
            ['monitoring/index',   'Monitoring Ujian', 'admin'],
            ['monitoring/block',   'Blokir Ujian', 'admin'],
            ['monitoring/unblock', 'Buka Blokir', 'admin'],
            ['monitoring/reset',   'Reset Attempt', 'admin'],
            ['monitoring/lihat',   'Lihat Detail', 'admin'],
        ];

        $allPermIds = [];
        $operatorPermIds = [];
        foreach ($permList as [$perm, $label, $group]) {
            $p = Permission::firstOrCreate(['permission' => $perm], ['label' => $label, 'group' => $group]);
            $allPermIds[] = $p->id;
            // operator: hanya datacenter (tanpa guru/sekolah), tanpa hapus
            if (in_array($group, ['umum', 'datacenter']) && !in_array($perm, ['sekolah/edit', 'guru/*'])) {
                $operatorPermIds[] = $p->id;
            }
        }
        $superAdmin->permissions()->sync($allPermIds);
        $admin->permissions()->sync($allPermIds);
        $operator->permissions()->sync($operatorPermIds);

        // --- Admin user ---
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Administrator', 'password' => Hash::make('password'),
                'role' => 'admin', 'role_id' => $superAdmin->id,
                'account_status' => 'active', 'is_aktif' => true,
            ]
        );

        // --- Sekolah ---
        Sekolah::updateOrCreate(['npsn' => '20200001'], [
            'nama_sekolah' => 'SMA Negeri 1 Modern',
            'jenjang' => 'SMA',
            'alamat' => 'Jl. Pendidikan No. 1',
            'kelurahan' => 'Sukamaju', 'kecamatan' => 'Cibinong',
            'kabupaten' => 'Bogor', 'provinsi' => 'Jawa Barat',
            'telepon' => '021-1234567', 'email' => 'info@sma1modern.sch.id',
            'kepala_sekolah' => 'Dr. Budi Santoso, M.Pd.',
            'nip_kepala_sekolah' => '197001012000031001',
        ]);

        // --- Tahun ajaran ---
        $ta = TahunAjaran::updateOrCreate(
            ['kode_tahun_ajaran' => '2526'],
            ['nama_tahun_ajaran' => '2025/2026', 'semester' => 'Ganjil', 'is_aktif' => true,
             'tanggal_mulai' => now()->setDate(2025, 7, 15), 'tanggal_selesai' => now()->setDate(2026, 6, 30)]
        );

        // --- Jurusan ---
        $ipa = Jurusan::firstOrCreate(['kode_jurusan' => 'IPA'], ['nama_jurusan' => 'Ilmu Pengetahuan Alam', 'singkatan' => 'IPA']);
        $ips = Jurusan::firstOrCreate(['kode_jurusan' => 'IPS'], ['nama_jurusan' => 'Ilmu Pengetahuan Sosial', 'singkatan' => 'IPS']);

        // --- Mapel ---
        $mapelData = [
            ['MTK', 'Matematika', 'Umum'],
            ['BIO', 'Biologi', 'Kejuruan', $ipa->id],
            ['FIS', 'Fisika', 'Kejuruan', $ipa->id],
            ['EKO', 'Ekonomi', 'Kejuruan', $ips->id],
            ['SEJ', 'Sejarah Indonesia', 'Umum'],
            ['BIN', 'Bahasa Indonesia', 'Umum'],
            ['BING', 'Bahasa Inggris', 'Umum'],
        ];
        $mapelMap = [];
        foreach ($mapelData as $row) {
            $m = MataPelajaran::firstOrCreate(['kode_mapel' => $row[0]], [
                'nama_mapel' => $row[1], 'kelompok' => $row[2],
                'jurusan_id' => $row[3] ?? null, 'tingkat' => 10,
            ]);
            $mapelMap[$row[0]] = $m;
        }

        // --- Guru ---
        $guruNames = ['Andi Wijaya', 'Sri Wahyuni', 'Hendra Pratama', 'Lina Marlina', 'Rudi Hartono'];
        $gurus = [];
        foreach ($guruNames as $i => $name) {
            $nip = '198001'.str_pad((string) ($i+1), 2, '0', STR_PAD_LEFT).'2000031000'.$i;
            $g = Guru::updateOrCreate(['nip' => $nip], [
                'nama_ptk' => $name,
                'email' => Str::slug($name, '.').'@sekolah.test',
                'jenis_kelamin' => $i % 2 ? 'P' : 'L',
                'jabatan' => 'Guru', 'status_kepegawaian' => 'PNS',
                'password' => Hash::make('password'),
                'is_aktif' => true,
            ]);
            $gurus[] = $g;
        }

        // --- Rombel ---
        $rombelNames = ['X IPA 1', 'X IPA 2', 'X IPS 1', 'XI IPA 1', 'XII IPS 1'];
        $rombels = [];
        foreach ($rombelNames as $i => $nm) {
            $tingkat = (int) ['X' => 10, 'XI' => 11, 'XII' => 12][explode(' ', $nm)[0]];
            $jurusan = str_contains($nm, 'IPA') ? $ipa : $ips;
            $r = RombonganBelajar::firstOrCreate(
                ['nama_rombel' => $nm, 'tahun_ajaran_id' => $ta->id],
                ['tingkat' => $tingkat, 'jurusan_id' => $jurusan->id, 'wali_kelas_id' => $gurus[$i % count($gurus)]->id, 'kapasitas' => 36]
            );
            $rombels[] = $r;
        }

        // --- Siswa ---
        $siswaNames = ['Ahmad Fauzi', 'Bunga Citra', 'Cahya Dewi', 'Dimas Eka', 'Eva Faridah',
                       'Galih Hidayat', 'Hilda Indah', 'Iwan Jaya', 'Joko Kurnia', 'Kartika Lestari',
                       'Mira Nurul', 'Nanda Oktavia', 'Putu Riadi', 'Rina Sari', 'Surya Tama'];
        foreach ($siswaNames as $i => $name) {
            $nisn = '0099' . str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT);
            $s = Siswa::updateOrCreate(['nisn' => $nisn], [
                'nis' => 'NIS'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'nama_siswa' => $name,
                'jenis_kelamin' => $i % 2 ? 'P' : 'L',
                'agama' => 'Islam',
                'password' => Hash::make('password'),
                'is_aktif' => true,
            ]);
            SiswaRombel::firstOrCreate([
                'siswa_id' => $s->id, 'tahun_ajaran_id' => $ta->id,
            ], [
                'rombongan_belajar_id' => $rombels[$i % count($rombels)]->id,
            ]);
        }

        // --- Question types (5 jenis FIXED) ---
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
        // Hapus jenis di luar 5 yang diizinkan
        QuestionType::whereNotIn('slug', array_keys($allowed))->delete();

        $pg    = QuestionType::where('slug', 'pg')->first();
        $pgk   = QuestionType::where('slug', 'pgk')->first();
        $fill  = QuestionType::where('slug', 'fill-blank')->first();
        $match = QuestionType::where('slug', 'penjodohan')->first();
        $bs    = QuestionType::where('slug', 'benar-salah')->first();

        // --- Topik + Soal MTK ---
        $topikAljabar = Topic::firstOrCreate(['slug' => 'aljabar'], [
            'topic' => 'Aljabar Dasar', 'mata_pelajaran_id' => $mapelMap['MTK']->id, 'tingkat' => 10,
        ]);

        $bank = [
            ['Penjumlahan sederhana', 'Berapakah hasil dari 25 + 17?', ['32', '42', '52', '62'], 1],
            ['Perkalian dasar', 'Berapakah hasil dari 12 × 8?', ['86', '88', '96', '108'], 2],
            ['Akar kuadrat', 'Akar kuadrat dari 144 adalah?', ['10', '11', '12', '13'], 2],
            ['Persamaan linear', 'Jika 3x + 5 = 20, maka x = ?', ['3', '4', '5', '6'], 2],
            ['Bangun datar', 'Luas persegi sisi 8 cm adalah?', ['16', '32', '48', '64'], 3],
        ];

        $createdQs = [];
        foreach ($bank as $row) {
            $q = Question::firstOrCreate(['title' => $row[0]], [
                'question' => $row[1],
                'question_type_id' => $pg->id,
                'mata_pelajaran_id' => $mapelMap['MTK']->id,
                'topic_id' => $topikAljabar->id,
                'tingkat' => 10, 'tingkat_kesulitan' => 'sedang',
                'created_by_guru_id' => $gurus[0]->id,
            ]);
            $q->options()->delete();
            foreach ($row[2] as $i => $opt) {
                QuestionOption::create([
                    'question_id' => $q->id, 'option_text' => $opt,
                    'is_correct' => $i === $row[3], 'order' => $i,
                ]);
            }
            $createdQs[] = $q;
        }

        // --- Quiz contoh ---
        $quiz = Quiz::firstOrCreate(['slug' => 'uts-mtk-x'], [
            'name' => 'UTS Matematika Kelas X',
            'description' => 'Ujian tengah semester matematika untuk kelas X.',
            'mata_pelajaran_id' => $mapelMap['MTK']->id,
            'rombongan_belajar_id' => $rombels[0]->id,
            'tahun_ajaran_id' => $ta->id,
            'created_by_guru_id' => $gurus[0]->id,
            'tingkat' => 10, 'duration' => 30,
            'pass_marks' => 60, 'max_attempts' => 1,
            'is_published' => true, 'show_score' => true,
            'valid_from' => now()->subDay(),
            'valid_upto' => now()->addDays(30),
        ]);
        foreach ($createdQs as $idx => $q) {
            QuizQuestion::firstOrCreate(
                ['quiz_id' => $quiz->id, 'question_id' => $q->id],
                ['marks' => 20, 'order' => $idx + 1]
            );
        }
        $quiz->update(['total_marks' => $quiz->questions()->sum('marks')]);
    }
}
