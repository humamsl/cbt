@extends('layouts.app')
@section('title', 'Pengaturan Aplikasi')
@section('breadcrumb', 'Admin / Pengaturan')

@section('content')
<x-page-header title="Pengaturan Aplikasi" subtitle="Identitas sekolah, logo, tema, tampilan, dan pencadangan data"/>

<div x-data="{ tab: 'identitas' }" class="space-y-6">

    {{-- Tab navigation (di luar form supaya bisa share state dengan tab backup/restore) --}}
    <div class="flex gap-1 bg-white rounded-xl p-1 shadow-soft border border-slate-100 w-fit overflow-x-auto">
        @foreach([
            'identitas' => ' Identitas Sekolah',
            'tampilan'  => ' Logo Sekolah',
            'login'     => ' Halaman Login',
            'aplikasi'  => ' Identitas Aplikasi',
            'ip'        => ' Proteksi IP',
            'backup'    => ' Backup',
            'restore'   => ' Restore',
        ] as $key => $label)
            <button type="button" @click="tab='{{ $key }}'"
                    :class="tab==='{{ $key }}' ? 'bg-brand-600 text-white shadow-soft' : 'text-ink-600 hover:bg-slate-100'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition whitespace-nowrap">
                {{ $label }}
            </button>
        @endforeach
    </div>

<form method="POST" action="{{ route('setting.update') }}" enctype="multipart/form-data"
      class="space-y-6"
      x-show="!['backup','restore'].includes(tab)">
    @csrf @method('PUT')

    {{-- ============ TAB IDENTITAS SEKOLAH ============ --}}
    <div x-show="tab==='identitas'" x-cloak class="card card-pad space-y-4">
        <div class="grid md:grid-cols-2 gap-4">
            <x-field name="npsn" label="NPSN" :value="$sekolah->npsn"/>
            <x-field name="nama_sekolah" label="Nama Sekolah" :value="$sekolah->nama_sekolah" required/>
            <x-field type="select" name="jenjang" label="Jenjang" :value="$sekolah->jenjang"
                     :options="['SD'=>'SD','SMP'=>'SMP','SMA'=>'SMA','SMK'=>'SMK','MA'=>'MA','MTs'=>'MTs']" required/>
            <x-field name="telepon" label="Telepon" :value="$sekolah->telepon"/>
            <x-field name="email" type="email" label="Email" :value="$sekolah->email"/>
            <x-field name="website" label="Website" :value="$sekolah->website"/>
            <x-field name="kepala_sekolah" label="Kepala Sekolah" :value="$sekolah->kepala_sekolah"/>
            <x-field name="nip_kepala_sekolah" label="NIP Kepala Sekolah" :value="$sekolah->nip_kepala_sekolah"/>
        </div>
        <x-field name="alamat" label="Alamat" :value="$sekolah->alamat"/>
        <div class="grid md:grid-cols-4 gap-4">
            <x-field name="kelurahan" label="Kelurahan" :value="$sekolah->kelurahan"/>
            <x-field name="kecamatan" label="Kecamatan" :value="$sekolah->kecamatan"/>
            <x-field name="kabupaten" label="Kabupaten" :value="$sekolah->kabupaten"/>
            <x-field name="provinsi" label="Provinsi" :value="$sekolah->provinsi"/>
        </div>
    </div>

    {{-- ============ TAB LOGO SEKOLAH ============ --}}
    <div x-show="tab==='tampilan'" x-cloak class="grid lg:grid-cols-2 gap-6">
        {{-- Logo aplikasi --}}
        <div class="card card-pad space-y-4">
            <h3 class="font-semibold text-ink-900">Logo Sekolah</h3>
            <p class="text-xs text-ink-500">Tampil di sidebar, header, dan halaman login. Format: PNG/JPG/SVG, max 2MB.</p>

            @if($app['logo'])
                <div class="rounded-xl bg-gradient-to-br from-slate-50 to-slate-100 p-6 grid place-items-center border border-slate-200">
                    <img src="{{ Storage::url($app['logo']) }}" alt="Logo" class="max-h-32 max-w-full object-contain">
                </div>
                <label class="flex items-center gap-2 text-xs text-rose-600">
                    <input type="checkbox" name="remove_file[]" value="logo" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                    Hapus logo saat ini
                </label>
            @else
                <div class="rounded-xl border-2 border-dashed border-slate-200 p-6 text-center text-sm text-ink-500">
                    Belum ada logo
                </div>
            @endif

            <input type="file" name="logo" accept="image/*" class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
            @error('logo')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        {{-- Favicon --}}
        <div class="card card-pad space-y-4">
            <h3 class="font-semibold text-ink-900">Favicon</h3>
            <p class="text-xs text-ink-500">Tampil di tab browser. Format: PNG/ICO/SVG, max 512KB. Disarankan 32×32 px.</p>

            @if($app['favicon'])
                <div class="rounded-xl bg-gradient-to-br from-slate-50 to-slate-100 p-6 grid place-items-center border border-slate-200">
                    <img src="{{ Storage::url($app['favicon']) }}" alt="Favicon" class="w-12 h-12 object-contain">
                </div>
                <label class="flex items-center gap-2 text-xs text-rose-600">
                    <input type="checkbox" name="remove_file[]" value="favicon" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                    Hapus favicon
                </label>
            @else
                <div class="rounded-xl border-2 border-dashed border-slate-200 p-6 text-center text-sm text-ink-500">
                    Belum ada favicon
                </div>
            @endif

            <input type="file" name="favicon" accept="image/*" class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
        </div>
    </div>

    {{-- ============ TAB LOGIN ============ --}}
    <div x-show="tab==='login'" x-cloak class="card card-pad space-y-4">
        <h3 class="font-semibold text-ink-900">Background Halaman Login</h3>
        <p class="text-xs text-ink-500">Gambar akan ditampilkan sebagai latar di halaman login. Format: PNG/JPG/WEBP, max 5MB. Saran ukuran 1920×1080.</p>

        @if($app['login_bg'])
            <div class="rounded-xl overflow-hidden border border-slate-200 relative h-48 bg-slate-100">
                <img src="{{ Storage::url($app['login_bg']) }}" alt="" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent"></div>
                <div class="absolute bottom-3 left-3 text-white text-xs font-semibold drop-shadow">Preview</div>
            </div>
            <label class="flex items-center gap-2 text-xs text-rose-600">
                <input type="checkbox" name="remove_file[]" value="login_bg" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                Hapus background saat ini (kembali ke gradient default)
            </label>
        @else
            <div class="rounded-xl border-2 border-dashed border-slate-200 p-12 text-center text-sm text-ink-500 bg-gradient-to-br from-slate-50 to-slate-100">
                Belum ada background custom — menggunakan gradient default
            </div>
        @endif

        <input type="file" name="login_bg" accept="image/*" class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">

        <div class="border-t border-slate-100 pt-4 grid md:grid-cols-2 gap-4">
            <x-field name="login_title" label="Judul Halaman Login" :value="$app['login_title']"
                     help="Headline besar di kiri halaman login"/>
            <x-field name="login_subtitle" label="Sub-judul" :value="$app['login_subtitle']"
                     help="Deskripsi pendek di bawah headline"/>
        </div>
    </div>

    {{-- ============ TAB APLIKASI ============ --}}
    <div x-show="tab==='aplikasi'" x-cloak class="card card-pad space-y-4">
        <h3 class="font-semibold text-ink-900">Identitas Aplikasi</h3>
        <div class="grid md:grid-cols-2 gap-4">
            <x-field name="app_name" label="Nama Aplikasi" :value="$app['app_name']" required
                     help="Tampil di judul tab browser & sidebar"/>
            <x-field name="app_tagline" label="Tagline" :value="$app['app_tagline']"/>
        </div>
        <x-field name="footer_text" label="Teks Footer" :value="$app['footer_text']"
                 placeholder="© 2026 Nama Sekolah. Hak cipta dilindungi."/>
    </div>

    {{-- ============ TAB PROTEKSI IP ============ --}}
    <div x-show="tab==='ip'" x-cloak class="space-y-4">
        @php
            // Sarankan 2 format CIDR dari IP saat ini
            $ipParts = explode('.', $currentIp);
            $saranSlash32 = $currentIp.'/32';
            $saranSlash24 = (count($ipParts) === 4) ? "{$ipParts[0]}.{$ipParts[1]}.{$ipParts[2]}.0/24" : null;
        @endphp

        <div class="card card-pad space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-ink-900">Aturan IP untuk Siswa <span class="text-xs font-normal text-ink-500">(Proteksi Ketat)</span></h3>
                <span x-show="$root.querySelector('[name=ip_protection_enabled]:checked')" class="badge-success">ON</span>
                <span x-show="!$root.querySelector('[name=ip_protection_enabled]:checked')" class="badge-muted">OFF</span>
            </div>
            <p class="text-xs text-ink-500">Jika diaktifkan, siswa hanya boleh masuk ujian (mode ketat) dari IP yang sesuai aturan CIDR di bawah.</p>
        </div>

        <div class="card card-pad space-y-3">
            <h4 class="font-semibold text-ink-900 text-sm">Diagnostik IP <span class="text-xs font-normal text-ink-500">(untuk troubleshooting)</span></h4>
            <div class="text-xs space-y-1">
                <div><strong>IP terbaca:</strong> <span class="font-mono text-brand-600">{{ $currentIp }}</span></div>
                @foreach($ipHeaders as $name => $val)
                    <div class="text-ink-600"><strong>{{ $name }}:</strong> <span class="font-mono">{{ $val ?: '—' }}</span></div>
                @endforeach
            </div>
            <div class="pt-2 border-t border-slate-100">
                <div class="text-xs font-semibold text-ink-700 mb-1.5">Saran aturan yang bisa dipakai:</div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="appendCidr('{{ $saranSlash32 }}')"
                            class="px-2.5 py-1 rounded bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 text-xs font-mono hover:bg-emerald-100">{{ $saranSlash32 }}</button>
                    @if($saranSlash24)
                        <button type="button" onclick="appendCidr('{{ $saranSlash24 }}')"
                                class="px-2.5 py-1 rounded bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 text-xs font-mono hover:bg-emerald-100">{{ $saranSlash24 }}</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="card card-pad space-y-3">
            <h4 class="font-semibold text-ink-900 text-sm">Atur IP Gateway / Subnet</h4>
            <p class="text-xs text-ink-500">Contoh bebas: <code class="bg-slate-100 px-1 rounded">192.168.10.0/24</code>, <code class="bg-slate-100 px-1 rounded">10.80.32.0/24</code>, <code class="bg-slate-100 px-1 rounded">36.88.120.14/32</code>. Bisa isi banyak aturan (pisahkan per baris).</p>

            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="ip_protection_enabled" value="0">
                <input type="checkbox" name="ip_protection_enabled" value="1"
                       @checked($app['ip_protection_enabled'])
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 w-4 h-4">
                <span class="text-sm font-medium text-ink-700">Aktifkan proteksi IP address</span>
            </label>
            <p class="text-[10px] text-ink-500">Saat OFF, daftar IP tetap tersimpan tetapi tidak dipakai untuk memblokir akses siswa.</p>

            <div>
                <label class="label" for="allowed_ips">IP Gateway / CIDR</label>
                <textarea name="allowed_ips" id="allowed_ips" rows="6"
                          placeholder="192.168.10.0/24&#10;10.10.0.0/16&#10;#"
                          class="input font-mono text-sm">{{ $app['allowed_ips'] }}</textarea>
                <p class="mt-1 text-[10px] text-ink-500">Jika proteksi ON, isi minimal satu IP/CIDR. Komentar dengan <code>#</code> di awal baris diabaikan.</p>
            </div>
        </div>

        <script>
            function appendCidr(val) {
                const ta = document.getElementById('allowed_ips');
                const sep = ta.value && !ta.value.endsWith('\n') ? '\n' : '';
                ta.value += sep + val;
                ta.focus();
            }
        </script>
    </div>

    {{-- Submit --}}
    <div class="card card-pad flex justify-end gap-2 sticky bottom-4 shadow-soft"
         x-show="!['backup','restore'].includes(tab)">
        <button type="reset" class="btn-secondary">Reset</button>
        <button type="submit" class="btn-primary">
            <x-icon name="check" class="w-4 h-4"/> Simpan Pengaturan
        </button>
    </div>
</form>

{{-- ============ TAB BACKUP ============ --}}
<div x-show="tab==='backup'" x-cloak class="grid lg:grid-cols-2 gap-6">
    <form method="POST" action="{{ route('backup.download') }}" class="card card-pad space-y-4">
        @csrf
        <h3 class="font-semibold text-ink-900">💾 Backup Data ke ZIP</h3>
        <p class="text-sm text-ink-600">
            Pilih modul yang ingin di-backup. Hasilnya 1 file <code>.zip</code> berisi data Excel
            & JSON yang bisa dipakai untuk migrasi atau restore di server lain.
        </p>
        <div class="space-y-2">
            <label class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                <input type="checkbox" name="modules[]" value="guru" checked
                       class="rounded text-brand-600 focus:ring-brand-500">
                <div>
                    <div class="font-semibold text-sm text-ink-900">Data Guru</div>
                    <div class="text-xs text-ink-500">NIP, nama, kontak, status kepegawaian, dsb.</div>
                </div>
            </label>
            <label class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                <input type="checkbox" name="modules[]" value="siswa" checked
                       class="rounded text-brand-600 focus:ring-brand-500">
                <div>
                    <div class="font-semibold text-sm text-ink-900">Data Siswa</div>
                    <div class="text-xs text-ink-500">NISN, nama, rombel, orang tua, dsb.</div>
                </div>
            </label>
            <label class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                <input type="checkbox" name="modules[]" value="bank-soal" checked
                       class="rounded text-brand-600 focus:ring-brand-500">
                <div>
                    <div class="font-semibold text-sm text-ink-900">Bank Soal</div>
                    <div class="text-xs text-ink-500">Soal lengkap dengan opsi & kunci jawaban (JSON).</div>
                </div>
            </label>
        </div>
        <div class="pt-3 border-t border-slate-100">
            <button class="btn-primary w-full justify-center">⬇ Download Backup ZIP</button>
        </div>
    </form>

    <div class="card card-pad space-y-3 text-sm">
        <h3 class="font-semibold text-ink-900">ℹ️ Tentang Backup</h3>
        <ul class="list-disc pl-5 text-ink-600 space-y-1">
            <li>File <code>guru.xlsx</code> dan <code>siswa.xlsx</code> kompatibel dengan menu Import masing-masing.</li>
            <li>File <code>bank-soal.json</code> berisi soal + opsi (HTML rich-text aman).</li>
            <li>Password user <strong>tidak di-export</strong> demi keamanan — saat restore, akun baru pakai NIP/NISN sebagai password default.</li>
            <li>Simpan file di lokasi aman & rutin (mingguan/bulanan).</li>
        </ul>
    </div>
</div>

{{-- ============ TAB RESTORE ============ --}}
<div x-show="tab==='restore'" x-cloak class="grid lg:grid-cols-2 gap-6">
    <form method="POST" action="{{ route('backup.restore') }}" enctype="multipart/form-data"
          class="card card-pad space-y-4">
        @csrf
        <h3 class="font-semibold text-ink-900">♻️ Restore dari Backup ZIP</h3>
        <p class="text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded p-2">
            ⚠️ Restore akan <strong>menambah & meng-update</strong> data berdasarkan NIP / NISN / judul soal.
            Pastikan ZIP berasal dari aplikasi CBT ini.
        </p>
        <label class="block">
            <span class="label">Pilih file backup (.zip)</span>
            <input type="file" name="file" accept=".zip" required
                   class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
            @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-ink-500">Maks. 50 MB.</p>
        </label>
        <button class="btn-primary w-full justify-center">Mulai Restore</button>

        @if(session('restoreSummary'))
            <div class="mt-3 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-sm">
                <div class="font-semibold text-emerald-800 mb-2">Ringkasan Restore</div>
                <ul class="text-xs text-ink-700 space-y-1">
                    @foreach(session('restoreSummary') as $mod => $r)
                        <li>
                            <strong class="capitalize">{{ $mod }}:</strong>
                            {{ $r['success'] ?? 0 }} sukses, {{ $r['failed'] ?? 0 }} gagal
                            @if(!empty($r['errors']))
                                <details class="mt-1">
                                    <summary class="text-rose-600 cursor-pointer">Lihat error</summary>
                                    <ul class="list-disc pl-5 text-rose-600 mt-1">
                                        @foreach(array_slice($r['errors'], 0, 20) as $err)<li>{{ $err }}</li>@endforeach
                                        @if(count($r['errors']) > 20)<li>... dan {{ count($r['errors']) - 20 }} lainnya</li>@endif
                                    </ul>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </form>

    <div class="card card-pad space-y-3 text-sm">
        <h3 class="font-semibold text-ink-900">ℹ️ Tentang Restore</h3>
        <ul class="list-disc pl-5 text-ink-600 space-y-1">
            <li><strong>Guru</strong>: data dengan NIP yang sudah ada → di-update; NIP baru → akun dibuat.</li>
            <li><strong>Siswa</strong>: data dengan NISN yang sudah ada → di-update; NISN baru → akun dibuat. Rombel dipasang otomatis kalau ada di TA aktif.</li>
            <li><strong>Bank Soal</strong>: kombinasi <em>judul + mapel</em> jadi kunci unik. Soal yang sudah ada → opsi di-reset & dibangun ulang.</li>
            <li>Untuk migrasi penuh: pastikan tabel <em>jurusan, tahun ajaran, mapel, rombel</em> di server tujuan sudah dibuat dulu.</li>
        </ul>
    </div>
</div>

</div> {{-- /Alpine wrapper --}}
@endsection
