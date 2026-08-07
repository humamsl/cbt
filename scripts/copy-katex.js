// Menyalin aset runtime KaTeX (css, js, font, auto-render) dari node_modules ke
// public/vendor/katex agar rumus matematika ter-render tanpa koneksi internet
// (tanpa CDN). Dipakai untuk menampilkan LaTeX hasil copy-paste (\( ... \),
// \[ ... \], $$ ... $$) di editor Bank Soal, preview, dan halaman ujian siswa.
import { cpSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname(fileURLToPath(import.meta.url));
const src = path.join(root, '..', 'node_modules', 'katex', 'dist');
const dest = path.join(root, '..', 'public', 'vendor', 'katex');

if (!existsSync(src)) {
    console.warn('KaTeX belum terpasang di node_modules — lewati penyalinan aset.');
    process.exit(0);
}

// katex.min.css memuat font lewat path relatif "fonts/..." → struktur folder
// harus dipertahankan persis seperti di dist.
const items = ['katex.min.css', 'katex.min.js', 'fonts', 'contrib/auto-render.min.js'];

rmSync(dest, { recursive: true, force: true });

for (const item of items) {
    const from = path.join(src, item);
    if (!existsSync(from)) continue;
    const to = path.join(dest, item);
    mkdirSync(path.dirname(to), { recursive: true });
    cpSync(from, to, { recursive: true });
}

console.log('KaTeX assets copied to public/vendor/katex');
