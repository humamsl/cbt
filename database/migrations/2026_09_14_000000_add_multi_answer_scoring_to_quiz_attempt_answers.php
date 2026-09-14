<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PGK (Pilihan Ganda Kompleks) dan Penjodohan sebelumnya dirender di
 * halaman ujian siswa persis seperti PG biasa -- radio pilih satu opsi --
 * padahal PGK bisa punya >1 jawaban benar sekaligus, dan Penjodohan perlu
 * memasangkan tiap item kiri ke satu item kanan. Akibatnya kedua jenis
 * soal itu TIDAK BISA dijawab sesuai maksudnya sama sekali, dan
 * penilaiannya keliru mencocokkan ke satu opsi "benar" saja.
 *
 * `answer_json` menampung jawaban berstruktur sesuai jenis soal (kolom
 * question_option_id & answer_text yang sudah ada TIDAK diubah, tetap
 * dipakai apa adanya untuk PG/Benar-Salah/Fill-blank):
 *   - PGK        : array id opsi yang dicentang siswa, mis. [85, 87, 89]
 *   - Penjodohan : peta {left_option_id: right_option_id_pilihan_siswa}
 *
 * `partial_score` menyimpan pecahan nilai (0.000 - 1.000) yang didapat
 * untuk satu soal -- dipakai untuk PGK & Penjodohan yang dinilai
 * proporsional. Jenis soal lain nilainya selalu 0 atau 1, sama seperti
 * is_correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempt_answers', function (Blueprint $t) {
            if (! Schema::hasColumn('quiz_attempt_answers', 'answer_json')) {
                $t->json('answer_json')->nullable()->after('question_option_id');
            }
            if (! Schema::hasColumn('quiz_attempt_answers', 'partial_score')) {
                $t->decimal('partial_score', 4, 3)->nullable()->after('is_correct');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempt_answers', function (Blueprint $t) {
            if (Schema::hasColumn('quiz_attempt_answers', 'answer_json')) {
                $t->dropColumn('answer_json');
            }
            if (Schema::hasColumn('quiz_attempt_answers', 'partial_score')) {
                $t->dropColumn('partial_score');
            }
        });
    }
};
