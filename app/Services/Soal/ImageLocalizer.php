<?php

namespace App\Services\Soal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * "Lokalisasi" gambar pada HTML soal: setiap <img src> yang menunjuk ke luar
 * (situs lain, mis. hasil copy-paste soal dari web) di-download ke disk public
 * folder `soal/` — folder yang sama dengan upload CKEditor — lalu src-nya
 * ditulis ulang menjadi "/storage/soal/<hash>.<ext>".
 *
 * Kenapa perlu: soal yang di-copy-paste dari web menyimpan URL asli situs
 * sumber di database. Saat situs sumber lambat / memblokir hotlink / mati,
 * gambar soal ikut hilang di perangkat siswa. Setelah dilokalisasi, gambar
 * dilayani dari server sendiri selamanya.
 *
 * Nama file = sha1 isi file → gambar yang sama tidak pernah tersimpan dobel,
 * dan menyimpan ulang soal tidak menumpuk file baru.
 *
 * Gagal download (timeout, 404, bukan gambar, host privat) → src asli
 * DIBIARKAN, tidak pernah merusak konten.
 */
class ImageLocalizer
{
    /** Cache per-instance: url → src lokal (atau null jika gagal/di-skip). */
    protected array $cache = [];

    protected const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    protected const MIME_EXT = [
        'image/png'     => 'png',
        'image/jpeg'    => 'jpg',
        'image/gif'     => 'gif',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
        'image/bmp'     => 'bmp',
    ];

    /**
     * Simpan byte gambar MENTAH (mis. gambar embedded yang diekstrak dari file
     * .docx / .xlsx saat import) ke storage/soal, kembalikan src root-relative
     * "/storage/soal/<hash>.<ext>". Null bila bukan gambar valid / terlalu besar.
     *
     * Memakai dedup sha1 & deteksi tipe yang sama dengan localizeHtml().
     */
    public function store(string $bytes): ?string
    {
        return $this->storeBytes($bytes);
    }

    /** Proses seluruh <img> dalam 1 blok HTML. Aman dipanggil untuk teks polos. */
    public function localizeHtml(?string $html): ?string
    {
        if ($html === null || $html === '' || stripos($html, '<img') === false) {
            return $html;
        }

        return preg_replace_callback(
            '~(<img[^>]*?\ssrc=["\'])([^"\']+)(["\'])~i',
            function ($m) {
                $local = $this->localizeSrc($m[2]);
                return $m[1].($local ?? $m[2]).$m[3];
            },
            $html
        );
    }

    /** @return string|null src lokal baru, atau null bila tidak perlu/tidak bisa diganti */
    protected function localizeSrc(string $src): ?string
    {
        $src = trim($src);
        if ($src === '') return null;
        if (array_key_exists($src, $this->cache)) return $this->cache[$src];

        // Sudah menunjuk storage sendiri → biarkan (SoalHtml yang membereskan
        // base path-nya saat render).
        if (str_contains($src, '/storage/')) {
            return $this->cache[$src] = null;
        }

        // Screenshot yang ter-paste sebagai base64 → simpan jadi file betulan
        // supaya HTML soal tidak membengkak.
        if (str_starts_with($src, 'data:image/')) {
            return $this->cache[$src] = $this->storeDataUri($src);
        }

        // &amp; dsb. pada URL di atribut HTML harus di-decode sebelum di-fetch
        $url = html_entity_decode($src, ENT_QUOTES | ENT_HTML5);
        if (! preg_match('~^https?://~i', $url)) {
            return $this->cache[$src] = null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $this->isPrivateHost($host)) {
            return $this->cache[$src] = null;
        }

        try {
            $res = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; CBT-ImageLocalizer)'])
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
            if (! $res->successful()) {
                \Illuminate\Support\Facades\Log::warning('ImageLocalizer: gagal unduh gambar (HTTP non-2xx)', [
                    'url' => $url, 'status' => $res->status(),
                ]);
                return $this->cache[$src] = null;
            }
            $bytes = $res->body();
        } catch (\Throwable $e) {
            // Direktori exception di sini SERING kali masalah infrastruktur
            // (mis. cURL error 77: curl.cainfo di php.ini menunjuk ke path CA
            // bundle yang tidak ada -- kejadian nyata di server ini, membuat
            // SEMUA gambar hasil copy-paste dari luar gagal ter-download tanpa
            // ada tanda apa pun ke guru), bukan sekadar "situs sumber mati".
            // Sebelumnya exception ini ditelan total (catch tanpa log), jadi
            // satu-satunya cara mendiagnosis adalah membongkar kode manual.
            \Illuminate\Support\Facades\Log::warning('ImageLocalizer: gagal unduh gambar (exception)', [
                'url' => $url, 'error' => $e->getMessage(),
            ]);
            return $this->cache[$src] = null;
        }

        return $this->cache[$src] = $this->storeBytes($bytes);
    }

    protected function storeDataUri(string $dataUri): ?string
    {
        if (! preg_match('~^data:image/[\w.+-]+;base64,(.+)$~is', $dataUri, $m)) {
            return null;
        }
        $bytes = base64_decode($m[1], true);
        return $bytes === false ? null : $this->storeBytes($bytes);
    }

    protected function storeBytes(string $bytes): ?string
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) return null;

        // Deteksi tipe dari ISI file (bukan header/ekstensi URL yang bisa bohong)
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        $ext  = self::MIME_EXT[$mime] ?? null;
        if (! $ext) return null;

        $path = 'soal/'.sha1($bytes).'.'.$ext;
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            $disk->put($path, $bytes);
        }

        // Root-relative — SoalHtml::render() menambahkan base path (mis. /cbt)
        // sesuai request yang sedang berjalan saat ditampilkan.
        return '/storage/'.$path;
    }

    /** Tolak host lokal/privat supaya server tidak disuruh fetch dirinya/LAN (SSRF). */
    protected function isPrivateHost(string $host): bool
    {
        if (in_array($host, ['localhost', '0.0.0.0', '[::1]', '::1'], true)) return true;

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        return str_ends_with($host, '.local') || str_ends_with($host, '.test');
    }
}
