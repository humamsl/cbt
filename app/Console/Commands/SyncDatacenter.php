<?php

namespace App\Console\Commands;

use App\Models\Guru;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Services\DatacenterClient;
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

    protected $description = 'Sinkronkan data referensi (sekolah/tahun ajaran/jurusan/mapel/rombel) dari aplikasi Data Center';

    public function handle(DatacenterClient $client): int
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

        $mapel = $client->mataPelajaran();
        foreach ($mapel as $m) {
            MataPelajaran::updateOrCreate(['id' => $m['id']], collect($m)->except(['id', 'created_at', 'updated_at'])->all());
        }
        $this->info(count($mapel).' mata pelajaran tersinkron.');

        $rombel = $client->rombel();
        foreach ($rombel as $r) {
            $attrs = collect($r)->except(['id', 'created_at', 'updated_at', 'jurusan', 'tahunAjaran'])->all();
            // wali_kelas_id (guru) belum tentu ada di cache lokal — hindari FK violation,
            // biarkan terisi otomatis nanti saat guru bersangkutan login.
            if (! empty($attrs['wali_kelas_id']) && ! Guru::whereKey($attrs['wali_kelas_id'])->exists()) {
                $attrs['wali_kelas_id'] = null;
            }
            RombonganBelajar::updateOrCreate(['id' => $r['id']], $attrs);
        }
        $this->info(count($rombel).' rombongan belajar tersinkron.');

        return self::SUCCESS;
    }
}
