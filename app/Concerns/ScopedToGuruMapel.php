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

    /** Apply scope ke query questions: WHERE mata_pelajaran_id IN (...) */
    protected function scopeBankSoalForUser(Builder $q, $user): Builder
    {
        if (! $this->shouldScope($user)) return $q;

        $ids = $this->guruMapelIds($user);
        return $q->whereIn('mata_pelajaran_id', $ids ?: [0]);
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
