// Menyalin aset runtime TinyMCE (skins, icons, themes, models, plugins) dari
// node_modules ke public/vendor/tinymce agar editor tetap berfungsi tanpa
// koneksi internet (tanpa CDN jsdelivr).
import { cpSync, rmSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname(fileURLToPath(import.meta.url));
const src = path.join(root, '..', 'node_modules', 'tinymce');
const dest = path.join(root, '..', 'public', 'vendor', 'tinymce');

const items = ['skins', 'icons', 'themes', 'models', 'plugins', 'tinymce.min.js'];

rmSync(dest, { recursive: true, force: true });

for (const item of items) {
    const from = path.join(src, item);
    if (!existsSync(from)) continue;
    cpSync(from, path.join(dest, item), { recursive: true });
}

console.log('TinyMCE assets copied to public/vendor/tinymce');
