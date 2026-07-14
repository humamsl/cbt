<?php

namespace App\Support;

/**
 * Normalisasi HTML soal/opsi sebelum dirender ke siswa.
 *
 * Konten soal lama di database masih menyimpan <img src> dengan URL ABSOLUT
 * dari komputer tempat soal dibuat, mis. "http://localhost/cbt/storage/soal/x.png"
 * atau "http://192.168.x.x/storage/...". URL seperti itu tidak bisa dijangkau
 * dari HP siswa (host berbeda), dan varian "http://" diblokir browser sebagai
 * mixed content saat aplikasi diakses lewat https — inilah penyebab gambar
 * soal sering tidak muncul di HP.
 *
 * Solusi: setiap src yang menunjuk ke /storage/... ditulis ulang menjadi
 * root-relative terhadap base path request yang SEDANG diakses siswa, jadi
 * selalu ikut host + skema halaman itu sendiri. Data URI (base64) dan URL
 * yang sudah relative tidak disentuh.
 */
class SoalHtml
{
    public static function render(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $base = rtrim(request()->getBaseUrl(), '/');

        // "http://apapun/.../storage/foo.png" atau "//host/storage/foo.png"
        // → "{base}/storage/foo.png"
        $html = preg_replace(
            '~(src=["\'])(?:https?:)?//[^"\']*?(/storage/[^"\']+)(["\'])~i',
            '$1'.$base.'$2$3',
            $html
        );

        // "src="storage/foo.png"" (relative tanpa leading slash) rusak di URL
        // bertingkat seperti /ujian/1/2 → jadikan root-relative juga.
        $html = preg_replace(
            '~(src=["\'])storage/([^"\']+)(["\'])~i',
            '$1'.$base.'/storage/$2$3',
            $html
        );

        return $html;
    }
}
