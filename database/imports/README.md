# Import Bank Soal dari Aplikasi CBT Versi Lama

Memindahkan bank soal dari aplikasi CBT lama (skema warisan `cbt`, dump
mysqldump/MariaDB) ke skema aplikasi ini.

Dikerjakan oleh command **`php artisan soal:import-legacy`**
([app/Console/Commands/ImportSoalLegacy.php](../../app/Console/Commands/ImportSoalLegacy.php)).

## Kenapa tidak lewat fitur import yang sudah ada

| Fitur | Menerima | Kenapa tidak cocok |
|---|---|---|
| Import Bank Soal (`/bank-soal/import`) | `.xlsx .docx .csv` maks 5 MB | Dump SQL ditolak validasi, dan ukurannya ratusan MB |
| Restore Backup (`/backup/restore`) | ZIP `bank-soal.json` | Hanya paham format export aplikasi ini sendiri |

Struktur database lama juga berbeda: **mapel & kelas disimpan di TOPIK**, dan
topik dihubungkan ke soal lewat tabel polimorfik `topicables` — bukan kolom
langsung di `questions` seperti sekarang.

## Cara pakai

### 1. Muat dump ke database staging

Boleh dump utuh; tabel yang tidak dipakai (`quiz_attempts` dsb) diabaikan.
Harus di server MySQL yang sama dengan database aplikasi.

```bash
mysql -uroot -e "CREATE DATABASE cbt_lama CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -uroot cbt_lama < dump-sekolah.sql
```

### 2. Lihat mapel yang ada di dump

Dump **tidak memuat tabel `mata_pelajaran`** — hanya id-nya. Jadi id itu harus
dicocokkan manual ke Data Center. Command ini menampilkan tiap id sumber
beserta jumlah soal & contoh nama topiknya sebagai petunjuk:

```bash
php artisan soal:import-legacy --source-db=cbt_lama --print-mapel
```

### 3. Susun file pemetaan JSON

Format `{"id mapel di dump": id mapel Data Center}`. Mapel sumber yang tidak
dicantumkan akan **dilewati**. Contoh untuk SMPN 218 ada di
[mapel-map-smpn218.json](mapel-map-smpn218.json).

Kalau mapel tujuan belum ada di Data Center, buat dulu lewat aplikasi Data
Center, baru pakai id-nya di sini.

### 4. Cek dulu, baru jalankan

```bash
php artisan soal:import-legacy --source-db=cbt_lama --mapel-map=map.json --dry-run
php artisan soal:import-legacy --source-db=cbt_lama --mapel-map=map.json --max-tingkat=9
```

`--dry-run` memperlihatkan rencananya per mapel tanpa menulis apa pun.

### Opsi lain

| Opsi | Guna |
|---|---|
| `--max-tingkat=9` | Batas tingkat wajar (9 untuk SMP, 12 untuk SMA). Topik dengan tingkat di atasnya digabung ke topik senama yang valid — memperbaiki bug entri data aplikasi lama yang menduplikasi topik dengan `class` naik terus |
| `--guru-id=5` | Isi `created_by_guru_id` pada soal hasil import |
| `--batch=nama` | Nama batch, default = nama database sumber. Berguna kalau satu instalasi menampung beberapa dump |
| `--rollback` | Tarik kembali seluruh hasil import batch ini |

**Aman diulang.** Setiap baris yang masuk dicatat di tabel
`legacy_soal_imports`, jadi menjalankan ulang tidak menggandakan soal — hanya
melanjutkan sisanya. Kalau import terputus di tengah jalan, jalankan lagi
perintah yang sama.

## Yang dipetakan

| Sumber (skema lama) | Tujuan (skema ini) |
|---|---|
| `questions.name` | `questions.question` |
| `topicables` (polimorfik) | `questions.topic_id` (kolom langsung) |
| `topics.name` / `.class` / `.id_mata_pelajaran` | `topics.topic` / `.tingkat` / `.mata_pelajaran_id` |
| `question_options.name` | `question_options.option_text` |
| `matching_answers` (pasangan kiri-kanan) | `question_options.pair_group` |
| opsi `is_correct` pada Fill the Blank | `questions.correct_answer_text` (pemisah `\|`) |
| `media_url` = `'url'` / `'media url'` | `NULL` (placeholder sampah di sumber) |

Jenis soal dicocokkan lewat **nama**, bukan id — supaya dump dengan urutan id
berbeda tetap terbaca. Padanan nama diatur di konstanta `ALIAS_JENIS`.

Yang **tidak** ikut: soal & topik ter-*soft-delete*, topik yang tidak memuat
satu pun soal aktif, serta paket ujian (`quizzes`, `quiz_questions`) — paket
ujian butuh pemetaan tambahan ke `rombongan_belajar` & `tahun_ajaran`. Tabel
`legacy_soal_imports` menyimpan peta id lama → id baru, jadi relasi paket→soal
masih bisa ditelusuri kalau nanti dibutuhkan.

Soal tanpa kunci jawaban di sumbernya tetap diimpor tapi **dinonaktifkan**
(`is_active = 0`) — isinya tidak hilang dan bisa dilengkapi guru, tapi tidak
akan terpilih ke paket ujian dan membuat semua siswa otomatis salah.

## Gambar soal — perlu langkah terpisah

Dump SQL hanya memuat HTML soalnya, **bukan file gambarnya**. Soal yang memuat
`<img src="/storage/...">` akan tampil rusak sampai filenya disalin.

Command melaporkan berapa soal yang terdampak di akhir proses. Salin folder
gambar dari server lama ke `storage/app/public/` dengan struktur folder
dipertahankan:

```bash
rsync -av user@server-lama:/path/cbt/storage/app/public/photos/ storage/app/public/photos/
```

`App\Support\SoalHtml::render()` sudah otomatis menyesuaikan base path-nya,
jadi isi soal tidak perlu diubah. Untuk gambar yang sumbernya URL eksternal
(hasil copy-paste dari web), unduh ke server sendiri dengan:

```bash
php artisan soal:localize-images
```

## Riwayat: import SMPN 218 (Agustus 2026)

Dump `cbt smpn218.sql` (31 Juli 2026), batch `cbt_src218`:

| | Jumlah |
|---|---|
| Soal | 9.479 |
| Opsi jawaban | 37.941 |
| Topik | 408 |
| Soal dinonaktifkan (tanpa kunci) | 5 |
| Mapel baru dibuat di Data Center | Prakarya (id 58), PAK & Budi Pekerti (id 59) |

Waktu jalan ±20 detik. 981 soal memuat gambar yang filenya belum disalin —
daftarnya di [gambar-soal-yang-dibutuhkan.txt](gambar-soal-yang-dibutuhkan.txt)
(796 file).
