<?php

namespace App\Services;

use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;

/**
 * Upsert data siswa/guru dari payload Data Center ke tabel cache lokal CBT.
 *
 * Logika ini dipakai di DUA tempat, dengan mapping yang SAMA:
 *   - AuthController  : lazy-sync 1 orang saat login (verify-siswa/verify-guru).
 *   - datacenter:sync : bulk-sync semua siswa/guru (endpoint /v1/siswa & /v1/guru).
 *
 * CBT tidak menyimpan password siswa/guru — autentikasi tetap diverifikasi ke
 * Data Center. FK (jurusan/wali kelas/rombel) dijaga agar tidak violation kalau
 * data induknya belum ada di cache lokal.
 */
class DatacenterSync
{
    public function upsertSiswa(array $data): Siswa
    {
        $siswa = Siswa::updateOrCreate(['id' => $data['id']], [
            'nisn' => $data['nisn'], 'nis' => $data['nis'] ?? null,
            'nama_siswa' => $data['nama_siswa'], 'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
            'tempat_lahir' => $data['tempat_lahir'] ?? null, 'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
            'agama' => $data['agama'] ?? null, 'alamat' => $data['alamat'] ?? null,
            'nomor_hp' => $data['nomor_hp'] ?? null, 'email' => $data['email'] ?? null,
            'nama_ayah' => $data['nama_ayah'] ?? null, 'nama_ibu' => $data['nama_ibu'] ?? null,
            'nomor_hp_ortu' => $data['nomor_hp_ortu'] ?? null, 'foto' => $data['foto'] ?? null,
            'is_aktif' => $data['is_aktif'] ?? true,
        ]);

        $rs = $data['rombel_sekarang'] ?? null;
        $rombelData = $rs['rombel'] ?? null;
        if ($rs && $rombelData) {
            $this->ensureTahunAjaranStub($rombelData['tahun_ajaran_id']);

            $jurusanId = $rombelData['jurusan_id'] ?? null;
            if ($jurusanId && ! Jurusan::whereKey($jurusanId)->exists()) $jurusanId = null;
            $waliId = $rombelData['wali_kelas_id'] ?? null;
            if ($waliId && ! Guru::whereKey($waliId)->exists()) $waliId = null;

            RombonganBelajar::updateOrCreate(['id' => $rombelData['id']], [
                'nama_rombel' => $rombelData['nama_rombel'], 'tingkat' => $rombelData['tingkat'],
                'jurusan_id' => $jurusanId, 'tahun_ajaran_id' => $rombelData['tahun_ajaran_id'],
                'wali_kelas_id' => $waliId, 'kapasitas' => $rombelData['kapasitas'] ?? 36,
            ]);

            SiswaRombel::updateOrCreate(['id' => $rs['id']], [
                'siswa_id' => $siswa->id,
                'rombongan_belajar_id' => $rombelData['id'],
                'tahun_ajaran_id' => $rombelData['tahun_ajaran_id'],
            ]);
        }

        return $siswa;
    }

    public function upsertGuru(array $data): Guru
    {
        $guru = Guru::updateOrCreate(['id' => $data['id']], [
            'nip' => $data['nip'], 'nama_ptk' => $data['nama_ptk'], 'email' => $data['email'] ?? null,
            'nomor_hp' => $data['nomor_hp'] ?? null, 'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
            'tempat_lahir' => $data['tempat_lahir'] ?? null, 'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
            'alamat' => $data['alamat'] ?? null, 'jabatan' => $data['jabatan'] ?? null,
            'status_kepegawaian' => $data['status_kepegawaian'] ?? null, 'foto' => $data['foto'] ?? null,
            'is_aktif' => $data['is_aktif'] ?? true,
        ]);

        foreach (($data['guru_mapel'] ?? []) as $gm) {
            $mapelData = $gm['mapel'] ?? null;
            if (! $mapelData) continue;

            MataPelajaran::updateOrCreate(['id' => $mapelData['id']], [
                'kode_mapel' => $mapelData['kode_mapel'] ?? ('MP-'.$mapelData['id']),
                'nama_mapel' => $mapelData['nama_mapel'] ?? ('Mapel #'.$mapelData['id']),
            ]);

            $rombelData = $gm['rombel'] ?? null;
            $rombelId = null;
            if ($rombelData) {
                $this->ensureTahunAjaranStub($gm['tahun_ajaran_id']);
                RombonganBelajar::updateOrCreate(['id' => $rombelData['id']], [
                    'nama_rombel' => $rombelData['nama_rombel'],
                    'tingkat' => $rombelData['tingkat'] ?? 10,
                    'tahun_ajaran_id' => $gm['tahun_ajaran_id'],
                ]);
                $rombelId = $rombelData['id'];
            }

            GuruMapel::updateOrCreate(['id' => $gm['id']], [
                'guru_id' => $guru->id,
                'mata_pelajaran_id' => $mapelData['id'],
                'rombongan_belajar_id' => $rombelId,
                'tahun_ajaran_id' => $gm['tahun_ajaran_id'],
            ]);
        }

        return $guru;
    }

    public function ensureTahunAjaranStub(int $id): void
    {
        TahunAjaran::firstOrCreate(['id' => $id], [
            'kode_tahun_ajaran' => 'TA-'.$id,
            'nama_tahun_ajaran' => 'Tahun Ajaran #'.$id,
            'semester' => 'Ganjil',
            'is_aktif' => false,
        ]);
    }
}
