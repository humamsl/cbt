<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * DEPRECATED — tidak lagi diperlukan.
 *
 * Sekolah/TahunAjaran/Jurusan/MataPelajaran/TingkatKelas/RombonganBelajar/Guru/
 * GuruMapel/Siswa/SiswaRombel sekarang terhubung LANGSUNG ke database Data
 * Center (connection 'mysql_datacenter', lihat App\Models\* & config/database.php)
 * — dibaca/ditulis real-time, tanpa API, tanpa cache lokal. Data baru yang
 * ditambahkan di Data Center langsung muncul di CBT tanpa perlu perintah apa
 * pun (tidak perlu cron, tidak perlu dijalankan manual).
 *
 * Command ini dibiarkan sebagai stub (bukan dihapus) supaya cron/scheduler lama
 * yang mungkin masih memanggil `datacenter:sync` tidak error — cukup keluar
 * langsung dengan pesan info.
 */
class SyncDatacenter extends Command
{
    protected $signature = 'datacenter:sync';

    protected $description = '[DEPRECATED] Tidak diperlukan lagi — data referensi & guru/siswa kini live via koneksi DB langsung ke Data Center.';

    public function handle(): int
    {
        $this->warn('datacenter:sync sudah TIDAK DIPERLUKAN LAGI.');
        $this->line('Sekolah/TahunAjaran/Jurusan/MataPelajaran/TingkatKelas/RombonganBelajar/Guru/GuruMapel/Siswa/SiswaRombel');
        $this->line('sekarang terhubung langsung (real-time) ke database Data Center — tidak ada lagi yang perlu disinkronkan.');
        $this->line('Kalau perintah ini dipanggil dari cron, aman dihapus dari crontab.');

        return self::SUCCESS;
    }
}
