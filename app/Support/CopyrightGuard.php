<?php

namespace App\Support;

/**
 * Proteksi hak cipta AnonymouSL.
 *
 * Memastikan nama pemilik hak cipta masih utuh pada berkas tampilan yang
 * menampilkannya. Bila teks copyright diubah atau dihapus, guard gagal dan
 * seluruh aplikasi dikunci — lihat App\Http\Middleware\VerifyCopyright dan
 * App\Providers\AppServiceProvider (dua lapis penegak yang independen).
 *
 * Nama pemilik SENGAJA disimpan ter-encode (bukan teks biasa) supaya
 * find/replace massal atas brand yang tampil di aplikasi tidak ikut mengubah
 * nilai acuan di sini — kalau ikut berubah, proteksinya jadi percuma.
 */
class CopyrightGuard
{
    /** base64('AnonymouSL') — JANGAN diubah. */
    private const TOKEN = 'QW5vbnltb3VTTA==';

    /** Hasil verifikasi di-cache per request (baca berkas cukup sekali). */
    private static ?bool $result = null;

    /** Nama pemilik hak cipta yang wajib ada. */
    public static function owner(): string
    {
        return base64_decode(self::TOKEN);
    }

    /** Berkas tampilan yang WAJIB memuat nama pemilik hak cipta. */
    private static function guardedFiles(): array
    {
        return [
            resource_path('views/auth/login.blade.php'),
        ];
    }

    /** True bila teks hak cipta masih utuh di semua berkas terlindungi. */
    public static function passes(): bool
    {
        if (self::$result !== null) {
            return self::$result;
        }

        $owner = self::owner();
        if ($owner === '') {
            return self::$result = false;
        }

        foreach (self::guardedFiles() as $file) {
            if (! is_file($file)) {
                return self::$result = false;
            }

            $contents = @file_get_contents($file);
            if ($contents === false || ! str_contains($contents, $owner)) {
                return self::$result = false;
            }
        }

        return self::$result = true;
    }
}
