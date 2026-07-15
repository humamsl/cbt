<?php

namespace App\Concerns;

use App\Models\GuruMapel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait untuk membatasi data agar guru hanya melihat mata pelajaran
 * & rombel yang sudah ditugaskan (via tabel guru_mapel).
 * Admin bypass filter.
 */
trait ScopedToGuruMapel
{
    /** Apakah user sekarang harus di-scope? (guru). Admin → false (lihat semua). */
    protected function shouldScope($user): bool
    {
        return ($user?->user_type ?? 'admin') === 'guru';
    }

    /** Daftar mata_pelajaran_id yang ditugaskan ke guru saat ini. */
    protected function guruMapelIds($user): array
    {
        if (! $this->shouldScope($user)) return [];

        return GuruMapel::where('guru_id', $user->id)
            ->pluck('mata_pelajaran_id')->unique()->values()->toArray();
    }

    /** Daftar rombel_id yang ditugaskan ke guru saat ini. */
    protected function guruRombelIds($user): array
    {
        if (! $this->shouldScope($user)) return [];

        return GuruMapel::where('guru_id', $user->id)
            ->whereNotNull('rombongan_belajar_id')
            ->pluck('rombongan_belajar_id')->unique()->values()->toArray();
    }

    /** Daftar tingkat (kelas) yang diajar guru — dari rombel di penugasan guru_mapel. */
    protected function guruTingkatList($user): array
    {
        if (! $this->shouldScope($user)) return [];

        $rombelIds = $this->guruRombelIds($user);
        if (empty($rombelIds)) return [];

        return \App\Models\RombonganBelajar::whereIn('id', $rombelIds)
            ->pluck('tingkat')->filter()->map(fn ($t) => (int) $t)
            ->unique()->values()->toArray();
    }

    /**
     * Apply scope ke query questions: guru hanya melihat soal pada
     * MAPEL yang diajarnya DAN TINGKAT kelas yang diajarnya.
     *
     * Catatan tingkat:
     *  - soal ber-tingkat → harus cocok dengan salah satu tingkat yang diajar
     *  - soal TANPA tingkat (null, banyak di data lama) → tetap terlihat,
     *    supaya soal lama tidak mendadak "hilang" dari guru
     *  - guru yang penugasannya belum punya rombel sama sekali → filter
     *    tingkat dilewati (fallback mapel saja), daripada bank soalnya kosong
     */
    protected function scopeBankSoalForUser(Builder $q, $user): Builder
    {
        if (! $this->shouldScope($user)) return $q;

        $q->whereIn('mata_pelajaran_id', $this->guruMapelIds($user) ?: [0]);

        $tingkatList = $this->guruTingkatList($user);
        if (! empty($tingkatList)) {
            $q->where(function ($x) use ($tingkatList) {
                $x->whereNull('tingkat')->orWhereIn('tingkat', $tingkatList);
            });
        }

        return $q;
    }

    /**
     * Dropdown tingkat (nomor => nama) sesuai hak user:
     * guru → HANYA tingkat kelas yang diajarnya; admin → semua tingkat.
     * Guru yang penugasannya belum punya rombel → semua (jangan kosongkan UI).
     */
    protected function tingkatDropdownFor($user): array
    {
        $all = \App\Models\TingkatKelas::dropdown();
        if (! $this->shouldScope($user)) return $all;

        $taught = $this->guruTingkatList($user);
        if (empty($taught)) return $all;

        return array_filter(
            $all,
            fn ($nama, $nomor) => in_array((int) $nomor, $taught, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** Guard tunggal: bolehkah guru mengakses/mengubah 1 soal tertentu? */
    protected function assertBolehKelolaSoal($user, \App\Models\Question $soal): void
    {
        if (! $this->shouldScope($user)) return;

        if (! in_array($soal->mata_pelajaran_id, $this->guruMapelIds($user), true)) {
            abort(403, 'Anda tidak mengajar mapel ini.');
        }

        $tingkatList = $this->guruTingkatList($user);
        if ($soal->tingkat && ! empty($tingkatList) && ! in_array((int) $soal->tingkat, $tingkatList, true)) {
            abort(403, 'Soal ini untuk tingkat kelas yang tidak Anda ajar.');
        }
    }

    /** Apply scope ke query quizzes: filter mapel + rombel guru */
    protected function scopeQuizForUser(Builder $q, $user): Builder
    {
        if (! $this->shouldScope($user)) return $q;

        $mapelIds = $this->guruMapelIds($user);
        $rombelIds = $this->guruRombelIds($user);

        return $q->whereIn('mata_pelajaran_id', $mapelIds ?: [0])
                 ->where(function ($x) use ($rombelIds) {
                     $x->whereNull('rombongan_belajar_id')
                       ->orWhereIn('rombongan_belajar_id', $rombelIds ?: [0]);
                 });
    }
}
