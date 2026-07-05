<?php

namespace App\Console\Commands;

use App\Models\Guru;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Models\TingkatKelas;
use App\Services\DatacenterClient;
use App\Services\DatacenterSync;
use Illuminate\Console\Command;

/**
 * Tarik data referensi (sekolah/tahun ajaran/jurusan/mapel/rombel) dari
 * aplikasi Data Center dan upsert ke tabel cache lokal.
 *
 * Login guru/siswa SUDAH self-heal tanpa perintah ini (lihat
 * AuthController::upsertSiswaFromDatacenter/upsertGuruFromDatacenter — dijalankan
 * otomatis tiap kali login). Command ini untuk memastikan dropdown mapel/rombel/
 * tahun-ajaran di layar Kelola Tes/Bank Soal/Topik sudah terisi walau belum ada
 * yang login, dan untuk me-refresh data referensi yang berubah di Data Center
 * (ganti nama mapel, tambah rombel baru, dst). Jalankan manual atau lewat scheduler.
 */
class SyncDatacenter extends Command
{
    protected $signature = 'datacenter:sync';

    protected $description = 'Sinkronkan data (sekolah/tahun ajaran/jurusan/tingkat kelas/mapel/guru/rombel/siswa) dari aplikasi Data Center';

    public function handle(DatacenterClient $client, DatacenterSync $sync): int
    {
        $sekolah = $client->sekolah();
        if ($sekolah) {
            Sekolah::updateOrCreate(['id' => $sekolah['id']], collect($sekolah)->except(['id', 'created_at', 'updated_at'])->all());
            $this->info('Sekolah tersinkron.');
        }

        $tahunAjaran = $client->tahunAjaran();
        foreach ($tahunAjaran as $ta) {
            TahunAjaran::updateOrCreate(['id' => $ta['id']], collect($ta)->except(['id', 'created_at', 'updated_at'])->all());
        }
        $this->info(count($tahunAjaran).' tahun ajaran tersinkron.');

        $jurusan = $client->jurusan();
        foreach ($jurusan as $j) {
            Jurusan::updateOrCreate(['id' => $j['id']], collect($j)->except(['id', 'created_at', 'updated_at'])->all());
        }
        $this->info(count($jurusan).' jurusan tersinkron.');

        // Tingkat kelas di-upsert by `kode` (natural key), bukan id, supaya tidak
        // bentrok dgn baris tingkat_kelas yang mungkin sudah ada lokal di CBT.
        $tingkat = $client->tingkatKelas();
        foreach ($tingkat as $t) {
            TingkatKelas::updateOrCreate(['kode' => $t['kode']], collect($t)->except(['id', 'created_at', 'updated_at'])->all());
        }
        $this->info(count($tingkat).' tingkat kelas tersinkron.');

        $mapel = $client->mataPelajaran();
        foreach ($mapel as $m) {
            MataPelajaran::updateOrCreate(['id' => $m['id']], collect($m)->except(['id', 'created_at', 'updated_at'])->all());
        }
        $this->info(count($mapel).' mata pelajaran tersinkron.');

        // Guru di-sync SEBELUM rombel supaya FK wali_kelas_id bisa langsung terisi.
        // upsertGuru sekaligus meng-upsert guru_mapel (penugasan mengajar).
        // Per baris dibungkus try/catch: 1 record bermasalah (mis. NIP invalid)
        // tidak membatalkan seluruh sinkronisasi.
        $guru = $client->allGuru();
        $okGuru = 0;
        foreach ($guru as $g) {
            try {
                $sync->upsertGuru($g);
                $okGuru++;
            } catch (\Throwable $e) {
                $this->warn("Guru #{$g['id']} (NIP {$g['nip']}) dilewati: ".$e->getMessage());
            }
        }
        $this->info("{$okGuru}/".count($guru).' guru tersinkron.');

        $rombel = $client->rombel();
        foreach ($rombel as $r) {
            $attrs = collect($r)->except(['id', 'created_at', 'updated_at', 'jurusan', 'tahunAjaran'])->all();
            // wali_kelas_id yg gurunya belum ada tetap dijaga agar tak FK violation.
            if (! empty($attrs['wali_kelas_id']) && ! Guru::whereKey($attrs['wali_kelas_id'])->exists()) {
                $attrs['wali_kelas_id'] = null;
            }
            RombonganBelajar::updateOrCreate(['id' => $r['id']], $attrs);
        }
        $this->info(count($rombel).' rombongan belajar tersinkron.');

        // Siswa di-sync TERAKHIR (butuh rombel sudah ada). upsertSiswa sekaligus
        // meng-upsert penempatan rombel siswa (rombel_sekarang).
        $siswa = $client->allSiswa();
        $okSiswa = 0;
        foreach ($siswa as $s) {
            try {
                $sync->upsertSiswa($s);
                $okSiswa++;
            } catch (\Throwable $e) {
                $this->warn("Siswa #{$s['id']} (NISN {$s['nisn']}) dilewati: ".$e->getMessage());
            }
        }
        $this->info("{$okSiswa}/".count($siswa).' siswa tersinkron.');

        return self::SUCCESS;
    }
}
