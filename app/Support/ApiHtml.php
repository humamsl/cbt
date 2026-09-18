<?php

namespace App\Support;

/**
 * Versi App\Support\SoalHtml khusus API mobile: HTML soal/opsi tidak pernah
 * dibuka di dalam halaman browser (tidak ada "origin halaman" untuk resolve
 * src root-relative seperti "/storage/soal/x.png"), jadi src gambar harus
 * ditulis ulang jadi URL ABSOLUT (skema+host+path) memakai config('app.url'),
 * bukan request()->getBaseUrl() yang relatif seperti SoalHtml.
 *
 * Aturan rewrite persis sama dengan SoalHtml (lihat docblock di sana) --
 * cuma prefix-nya beda.
 */
class ApiHtml
{
    public static function render(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $base = rtrim((string) config('app.url'), '/');

        $html = preg_replace(
            '~(src=["\'])(?:(?:https?:)?//[^"\']*?|/[^"\']*?)?(/storage/[^"\']+)(["\'])~i',
            '$1'.$base.'$2$3',
            $html
        );

        $html = preg_replace(
            '~(src=["\'])storage/([^"\']+)(["\'])~i',
            '$1'.$base.'/storage/$2$3',
            $html
        );

        return $html;
    }
}
