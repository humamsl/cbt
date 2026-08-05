# Import Bank Soal dari Aplikasi CBT Versi Lama

Memindahkan bank soal dari aplikasi CBT lama (skema warisan `cbt`, dump
mysqldump/MariaDB) ke skema aplikasi ini.

## Dua jalur

**A. Upload ZIP lewat halaman Setting → Restore Backup.** Paling mudah, tanpa
SSH. Cocok kalau file ZIP-nya sudah disiapkan. Untuk SMPN 218 filenya sudah
ada: [bank-soal-smpn218.zip](bank-soal-smpn218.zip) (1,22 MB, 9.479 soal).

Syarat: mata pelajaran di ZIP dicocokkan lewat **kode mapel**, jadi kode-kode
ini harus sudah ada di Data Center — `PAIBP PPKN BIN MTK IPA IPS BING PJOK INF
SBD` plus **`PRA`** (Prakarya) dan **`PAKBP`** (Pendidikan Agama Kristen & Budi
Pekerti) yang biasanya belum ada. Kalau kodenya tidak ketemu, soalnya tetap
masuk tapi tanpa mata pelajaran.

Restore aman diulang: soal yang isinya sudah sama persis dilewati, bukan
digandakan atau ditimpa.

**B. Artisan command `soal:import-legacy`** — langsung dari dump `.sql`.
Dipakai kalau belum ada ZIP-nya, atau untuk sekolah lain yang migrasi dari
aplikasi lama yang sama. Butuh SSH + memuat dump ke database staging.
Selengkapnya di bawah.

---

# Jalur B — `php artisan soal:import-legacy`

[app/Console/Commands/ImportSoalLegacy.php](../../app/Console/Commands/ImportSoalLegacy.php)

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

## Perbaikan pada fitur Backup/Restore (Agustus 2026)

Jalur A di atas sebelumnya tidak bisa dipakai. Tiga bug di
[BackupService](../../app/Services/Backup/BackupService.php) diperbaiki
bersamaan dengan migrasi ini — ketiganya juga berdampak pada backup rutin
sekolah, bukan hanya migrasi:

1. **Soal disamakan berdasarkan judul.** `firstOrCreate(['title',
   'mata_pelajaran_id'])` menganggap semua soal berjudul sama sebagai satu soal
   lalu menimpa opsinya berulang. Judul kembar itu lumrah ("Chapter 4" 105
   soal, "ASTS Ganjil" 100 soal). Pada uji restore bank soal ini, **4.981 dari
   9.499 soal (52%) lenyap**. Sekarang soal dikenali dari isinya (judul +
   pertanyaan + mapel + tingkat).
2. **Topik tidak pernah dibuat.** Restore hanya mencocokkan nama topik yang
   kebetulan sudah ada, jadi di instalasi baru semua soal masuk tanpa topik.
   Sekarang topik dibuat otomatis kalau belum ada.
3. **`correct_answer_text` tidak ikut diekspor**, sehingga setiap backup
   kehilangan kunci jawaban soal Fill the Blank. Sekarang ikut, bersama
   `case_sensitive` dan kolom media.

Selain itu export ditulis bertahap ke file (memori puncak turun dari 202 MB ke
46 MB) dan restore memakai bulk insert dalam satu transaksi — 9.499 soal dari
171 detik menjadi 21 detik, cukup aman dari `max_execution_time`.

Format JSON naik ke **v2** (`topic` jadi objek berisi nama + mapel + tingkat).
Berkas backup v1 lama tetap bisa direstore.

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
