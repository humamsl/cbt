<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bersihkan jenis soal sehingga HANYA tersisa 5:
 *   pg, pgk, fill-blank, penjodohan, benar-salah
 *
 * Pastikan jenis "benar-salah" tetap ada (di-create ulang jika sebelumnya terhapus).
 * Soal yang sebelumnya pakai jenis lain di-reassign ke "pg" agar tidak orphan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $allowed = [
            'pg'          => 'Pilihan Ganda',
            'pgk'         => 'Pilihan Ganda Kompleks',
            'fill-blank'  => 'Fill the Blank',
            'penjodohan'  => 'Penjodohan',
            'benar-salah' => 'Benar / Salah',
        ];

        // 1. Pastikan 5 jenis allowed eksis (atau di-create ulang)
        foreach ($allowed as $slug => $name) {
            DB::table('question_types')->updateOrInsert(
                ['slug' => $slug],
                ['question_type' => $name, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $keepIds = DB::table('question_types')
            ->whereIn('slug', array_keys($allowed))
            ->pluck('id')->toArray();

        // 2. Re-assign soal lama yang pakai jenis non-allowed → ke "pg"
        $pgId = DB::table('question_types')->where('slug', 'pg')->value('id');
        DB::table('questions')
            ->whereNotIn('question_type_id', $keepIds)
            ->update(['question_type_id' => $pgId]);

        // 3. Hapus jenis di luar allowed
        DB::table('question_types')
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    public function down(): void
    {
        // No-op: tidak mengembalikan jenis lama (Pilihan Ganda lama, Multi, Esai).
    }
};
