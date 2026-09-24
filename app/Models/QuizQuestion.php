<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $guarded = ['id'];

    public function quiz() { return $this->belongsTo(Quiz::class); }
    public function question() { return $this->belongsTo(Question::class); }

    /**
     * Apakah soal ini punya cara untuk dijawab siswa. Fill-blank SELALU bisa
     * dijawab (kotak isian ditampilkan apa adanya) walau guru belum mengisi
     * kunci jawaban -- kosongnya kunci jawaban cuma bikin soal itu otomatis
     * dinilai salah (lihat ExamScoringService::answerScoreFraction()), BUKAN
     * alasan untuk menyembunyikan/melewati soalnya dari ujian. Tipe lain
     * (PG/PGK/Penjodohan) baru dianggap tidak bisa dijawab kalau opsinya
     * benar-benar kosong (tidak ada apa pun untuk dipilih siswa). Satu sumber
     * kebenaran dipakai bersama oleh tampilan (skip dari hitungan
     * wajib-jawab-semua) dan validasi submit (server tidak boleh mewajibkan
     * siswa menjawab soal yang memang tidak mungkin dijawab -- soal terhapus,
     * atau PG/PGK/Penjodohan tanpa opsi sama sekali).
     */
    public function isAnswerable(): bool
    {
        $q = $this->question;
        if (! $q) return false;

        $typeSlug = strtolower((string) (optional($q->type)->slug ?? optional($q->type)->question_type ?? ''));
        $isFillBlank = $typeSlug === 'fill-blank' || $typeSlug === 'fill_blank' || str_contains($typeSlug, 'fill');

        if ($isFillBlank) {
            return true;
        }

        if ($typeSlug === 'penjodohan') {
            return $q->options->where('is_left_side', true)->isNotEmpty()
                && $q->options->where('is_left_side', false)->isNotEmpty();
        }

        return $q->options->isNotEmpty();
    }
}
