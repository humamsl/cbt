# CBT Sekolah v2 — Laravel 11 + Tailwind

Aplikasi CBT (Computer Based Test) & Datacenter sekolah dengan tampilan modern.
Direplikasi dari proyek `web/cbt` & `web/datacenter`, dengan UI Tailwind professional.

## Modul

**Datacenter (data master):**
- Profil Sekolah
- Tahun Ajaran (multi-tahun, semester Ganjil/Genap)
- Jurusan / Program Keahlian
- Mata Pelajaran
- Rombongan Belajar (kelas) + wali kelas
- Data Guru (PTK)
- Data Siswa + penempatan rombel per TA

**CBT:**
- Topik / Sub Bab
- Bank Soal (PG, multi, B/S, esai)
- Tes / Ujian — pilih soal dari bank, set durasi & periode, publish
- Token Sesi
- Halaman Ujian Siswa (auto-save jawaban, timer, navigasi soal)
- Hasil & Laporan + detail jawaban
- Riwayat siswa

**3 role:** Admin (email) / Guru (NIP) / Siswa (NISN).

## Instalasi

```bash
cd cbt-v2

# 1. Install dependencies PHP & JS
composer install
npm install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Set DB di .env (default: cbt_v2 di MySQL Laragon)
#    DB_DATABASE=cbt_v2 — buat dulu database kosong dgn nama ini

# 4. Migrate + seed data contoh
php artisan migrate --seed

# 5. Build asset (Tailwind)
npm run build      # production
# atau: npm run dev (hot reload saat development)

# 6. Jalankan server
php artisan serve
# Buka http://localhost:8000
```

## Akun Default (setelah seeding)

| Role  | Username                | Password   |
|-------|  -----| --|
| Admin | `admin@sekolah.test`    | `password` |
| Guru  | NIP guru (lihat `Data Guru`) | `password` |
| Siswa | NISN siswa (lihat `Data Siswa`) | `password` |

Contoh NISN: `009900000001` (Ahmad Fauzi), `009900000002` (Bunga Citra), dst.

## Struktur Folder

```
app/
  Http/Controllers/
    AuthController.php
    DashboardController.php
    ProfilController.php
    Datacenter/
      SekolahController.php
      TahunAjaranController.php
      JurusanController.php
      MataPelajaranController.php
      RombelController.php
      GuruController.php
      SiswaController.php
    Cbt/
      TopikController.php
      BankSoalController.php
      TesController.php
      TokenSesiController.php
      HasilController.php
      UjianController.php       ← halaman ujian siswa
  Http/Middleware/RoleMiddleware.php
  Models/                       ← User, Guru, Siswa, Quiz, Question, dll.

resources/views/
  auth/login.blade.php          ← Login modern dengan tab role
  layouts/app.blade.php         ← Master layout (sidebar + header)
  partials/
    sidebar.blade.php
    header.blade.php
  components/
    icon.blade.php              ← Icon set (inline SVG)
    stat-card.blade.php         ← Card statistik dashboard
    field.blade.php             ← Form field reusable
    page-header.blade.php
  dashboard/{admin,siswa}.blade.php
  datacenter/{sekolah,tahun-ajaran,jurusan,mapel,rombel,guru,siswa}/
  cbt/{topik,bank-soal,tes,token-sesi,hasil,ujian}/

database/migrations/
  0001_01_01_000000_create_users_table.php
  0001_01_01_000001_create_cache_table.php
  0001_01_01_000002_create_jobs_table.php
  2024_01_01_100000_create_datacenter_tables.php
  2024_01_01_200000_create_cbt_tables.php
```

## UI / Theme

- **Tailwind CSS 3.4** + plugin Forms & Typography
- **Alpine.js 3** untuk interaktivitas ringan (sidebar mobile, timer ujian)
- Tema warna: `brand` (biru) primary, dengan emerald/amber/rose/sky untuk accent
- Komponen Blade reusable (`<x-icon>`, `<x-stat-card>`, `<x-field>`, `<x-page-header>`)
- Font Inter (via Bunny Fonts)

## Fitur Halaman Ujian

- Auto-save tiap pilih jawaban via fetch ke endpoint `saveAnswer`
- Timer countdown realtime; auto-submit ketika 00:00:00
- Navigasi soal sidebar dengan badge "terjawab/belum"
- Form submit "Selesai & Kirim" → kalkulasi nilai otomatis di server

## Fitur Proteksi (port dari `sigap2025new`)

Aplikasi diperkuat dengan **7 lapis proteksi** untuk keamanan dan integritas ujian:

### 1. Lisensi Aplikasi (`CheckAppExpiry`)
Tanggal kadaluwarsa dienkripsi dengan APP_KEY → disimpan di `APP_EXPIRE_DATE`.
```bash
php artisan app:license 2026-12-31         # encrypt + tampilkan
php artisan app:license unlimited --write  # auto tulis ke .env
```
Jika lewat tanggal atau key tidak cocok → halaman `errors/expired.blade.php` (403).

### 2. Status Akun (`AccountStatus`)
Field `account_status` di tabel users/guru/siswa: `active` | `suspended` | `locked` | `inactive`.
Selain `active` → user di-logout & diarahkan ke halaman peringatan yang sesuai.

### 3. OTP / Verifikasi 2 Langkah (`OtpVerification`)
- Set `otp_enabled = true` di user → wajib verifikasi kode 6 digit setelah login
- Method: `email` (default, masuk ke log saat dev) / `wa` (siapkan gateway)
- Kode kadaluwarsa 5 menit, resend cooldown 60 detik
- Mode dev: kode otomatis ditampilkan di halaman verifikasi

### 4. RBAC (`Rbac` middleware)
- Tabel `roles`, `permissions`, `role_permissions`
- Format permission: `page/action` (mis. `bank-soal/index`, `tes/*`)
- `$user->canAccess('bank-soal/edit')` cek granular
- Default role tersedia: `super-admin`, `admin`, `operator`
- Guru otomatis dapat permission CBT + Datacenter (tanpa hapus sekolah)
- Siswa hanya akses ujian + profil

### 5. Login Throttle & Lockout
- Per-IP rate limit 30/menit (Laravel rate limiter)
- Per-akun: 5x salah → akun dikunci 15 menit (kolom `locked_until`)
- Reset counter otomatis saat sukses login
- Semua percobaan tercatat di tabel `login_attempts`

### 6. Tracking Last Seen (`UpdateUserLastSeen`)
- Update `last_seen_at` maksimal 1x/menit (cache-based throttle)
- Bisa dipakai widget "online sekarang"

### 7. Anti-Cheat Ujian
Halaman ujian siswa terproteksi dari berbagai metode kecurangan:
| Pelanggaran | Deteksi |
| ---|---------|
| Tab switch | `visibilitychange` event |
| Window blur | `window.blur` event |
| Keluar fullscreen | `fullscreenchange` event |
| Copy / Paste / Cut | event listener |
| Klik kanan | `contextmenu` |
| F12 / Ctrl+Shift+I / Ctrl+U / Ctrl+S | keydown filter |
| DevTools terbuka | heuristic dimensi window |
| Reload accidental | `beforeunload` |
| Pindah perangkat/IP | cek IP attempt aktif |

Setiap pelanggaran:
1. Dicatat di tabel `exam_violations`
2. Counter naik & tampil di header ujian
3. Banner peringatan merah muncul 5 detik
4. Jika ≥ batas (default 5, bisa di-set per quiz di `settings.max_violations`) → **otomatis force submit**, kolom `is_force_submitted = true`

### Mengaktifkan untuk akun tertentu

```sql
-- Aktifkan OTP untuk seorang guru
UPDATE guru SET otp_enabled = 1, otp_method = 'email' WHERE nip = '198001012000031000';

-- Suspend siswa
UPDATE siswa SET account_status = 'suspended' WHERE nisn = '009900000001';

-- Set role admin custom (super-admin/admin/operator)
UPDATE users SET role_id = 1 WHERE email = 'admin@sekolah.test';
```

## Data Guru & Tingkat Kelas

**Menu Admin → Data Center → Data Guru** untuk men-set guru mengajar **mapel** apa, di **tingkat kelas** mana, dan **rombel** mana. Dengan ini:

- **Guru** hanya bisa lihat & buat **bank soal** pada mapel yang diajarkan
- **Guru** hanya bisa lihat & buat **tes/registrasi ujian** untuk mapel + rombel yang diajarkan
- **Admin** tetap bisa lihat semua (bypass)

Modul **Tingkat Kelas** (menu di Data Center) berisi master tingkat 1–12 dengan jenjang (SD/SMP/SMA/SMK). Otomatis di-seed saat `db:seed`.

## Single Sign-On Protection

Setiap user (admin/guru/siswa) hanya bisa **aktif di satu perangkat** dalam satu waktu. Jika login dari device baru:
- Sesi lama otomatis di-invalidate
- Saat user lama request berikutnya → **forced logout** + alert merah "Akun Anda aktif di perangkat lain"

Implementasi: kolom `current_session_id` di tabel user + middleware `SingleSessionGuard` yang membandingkan session_id.

## Log Login Admin

**Menu Admin → Log Login** menampilkan riwayat percobaan login dengan:
- Waktu, Username, Role (admin/guru/siswa), Status (sukses/gagal)
- Percobaan ke-berapa, Device type (desktop/mobile/tablet), Browser, OS, IP
- User Agent lengkap (truncated, hover untuk lihat full)
- Filter: username, role, status, device, tanggal
- Statistik: total, sukses, gagal, hari ini

## Pengaturan Aplikasi (Admin)

Menu **Administrasi → Pengaturan Aplikasi** (hanya admin) berisi 4 tab:

1. **🏫 Identitas Sekolah** — NPSN, nama, jenjang, alamat lengkap, kepala sekolah, kontak
2. **🎨 Logo & Tampilan** — upload logo aplikasi, favicon, dan **8 preset warna tema** + color picker
3. **🚪 Halaman Login** — upload background image custom (atau pakai gradient default), edit judul & sub-judul login
4. **⚙️ Identitas Aplikasi** — nama aplikasi, tagline, teks footer

Semua perubahan langsung diterapkan ke seluruh halaman via View Composer (`AppServiceProvider`). Logo dipakai di **sidebar, header, login**; warna tema diset via CSS variable `--brand`; background login langsung dipasang sebagai `background-image` di hero kiri.

**WAJIB jalankan sekali setelah install** agar file upload bisa diakses publik:
```bash
php artisan storage:link
```

Setelah itu Anda bisa upload logo PNG/SVG, favicon, dan background JPG/PNG/WEBP via Settings — semuanya tersimpan di `storage/app/public/settings/`.

## 5 Jenis Soal yang Didukung

| Slug | Nama | Cara Jawab |
|------|------| -|
| `pg` | Pilihan Ganda | Pilih 1 dari A–E |
| `pgk` | Pilihan Ganda Kompleks | Centang ≥1 jawaban benar |
| `fill-blank` | Fill the Blank | Ketik teks (case-insensitive opsional, multi-jawaban dipisah `\|`) |
| `penjodohan` | Penjodohan | Pasangkan kolom kiri ↔ kanan |
| `benar-salah` | Benar / Salah | Pilih B atau S |

Form Buat Soal otomatis berganti UI berdasarkan jenis (Alpine.js switch).

## Import / Export Bank Soal

Tersedia di halaman **CBT → Bank Soal → tombol Import / Export**.

### Import (`.xlsx`, `.xls`, `.csv`, `.docx`)
- Tombol **Unduh Template Excel** menghasilkan file `template-import-soal.xlsx` dengan 5 contoh baris untuk tiap jenis
- Kolom Excel wajib: `jenis | mapel_kode | tingkat | tingkat_kesulitan | judul | pertanyaan | opsi_a..e | jawaban | pembahasan`
- Format Word: blok soal dipisah `---`, tiap field dengan prefix `#JENIS:`, `#SOAL:`, `#JAWABAN:`, dst.
- Setiap baris/blok divalidasi; baris gagal ditampilkan di hasil import (tidak mengganggu yang sukses)

### Export
- **Word (`.docx`)** — format kompatibel dengan importer (bisa di-import balik)
- **PDF (soal saja)** — untuk dicetak sebagai naskah ujian offline
- **PDF + kunci jawaban** — untuk arsip guru

Filter aktif di halaman index (search, mapel, jenis) ikut ter-apply ke export.

### Dependency baru (sudah ada di composer.json)
```
phpoffice/phpspreadsheet  ^3.5
phpoffice/phpword         ^1.3
barryvdh/laravel-dompdf   ^3.0
```
Setelah pull update, jalankan: `composer install`

## Pengembangan Lanjutan (TODO)

- Import soal dari Excel
- Generate password siswa massal + export Excel
- Backup / Restore database
- Monitoring ujian realtime (Livewire/WebSocket)
- Export laporan PDF
- Migrasi data dari `cbt` & `datacenter` lama

## Lisensi

MIT — bebas digunakan dan dimodifikasi untuk kebutuhan sekolah.
