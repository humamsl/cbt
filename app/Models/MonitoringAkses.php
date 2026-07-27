<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pasangan guru ↔ rombel: hak guru (petugas/proktor) untuk memonitoring
 * ujian yang menarget rombel tsb. Tabel hidup di database CBT; guru &
 * rombel dirujuk lintas koneksi ke Data Center (tanpa FK).
 */
class MonitoringAkses extends Model
{
    protected $table = 'monitoring_akses';
    protected $guarded = ['id'];

    public function guru() { return $this->belongsTo(Guru::class); }
    public function rombel() { return $this->belongsTo(RombonganBelajar::class, 'rombongan_belajar_id'); }

    /** Daftar rombel_id yang boleh dimonitoring seorang guru. */
    public static function rombelIdsUntukGuru($guruId): array
    {
        return static::where('guru_id', $guruId)
            ->pluck('rombongan_belajar_id')->unique()->values()->all();
    }
}
