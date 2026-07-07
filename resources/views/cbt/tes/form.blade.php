@extends('layouts.app')
@section('title', $item->exists ? 'Edit Registrasi Ujian' : 'Tambah Registrasi Ujian')

@section('content')
<x-page-header :title="$item->exists ? 'Edit Registrasi Ujian' : 'Tambah Ujian Siswa'"
               :subtitle="'Atur target peserta, mapel, durasi & proteksi'"/>

@php
    $proteksiOptions = [
        'logout_otomatis' => 'Logout Otomatis',
        'blokir'          => 'Blokir Otomatis',
        'peringatan'      => 'Peringatan Saja',
        'tanpa_proteksi'  => 'Off',
    ];
    $defaultMode = old('target_mode', $item->target_mode ?? 'per_kelas');
    $defaultTingkat = old('target_tingkat.0', ($item->target_tingkat[0] ?? null));
@endphp

<form method="POST" action="{{ $item->exists ? route('tes.update', $item) : route('tes.store') }}"
      class="card card-pad space-y-5"
      x-data="tesForm({
          mode: '{{ $defaultMode }}',
          selectedRombels: @js(old('rombongan_belajar_ids', $selectedRombelIds ?? [])),
          rombels: @js($rombel->map(fn($r) => ['id' => $r->id, 'nama' => $r->nama_rombel, 'tingkat' => $r->tingkat])->toArray()),
      })">
    @csrf @if($item->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-sm">
            <div class="font-semibold text-rose-700 mb-1">Gagal menyimpan, periksa lagi:</div>
            <ul class="list-disc pl-5 text-rose-600 text-xs space-y-0.5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Nama Ujian --}}
    <x-field name="name" label="Nama Ujian" :value="$item->name" required placeholder="Contoh: ASTS Ganjil"/>

    {{-- Mata Pelajaran + Metode Target --}}
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="label" for="mata_pelajaran_id">
                Mata Pelajaran
                @if(! $canPilihUjianUmum)<span class="text-rose-500">*</span>@endif
            </label>
            <select name="mata_pelajaran_id" id="mata_pelajaran_id" class="select">
                @if($canPilihUjianUmum)
                    <option value="" {{ old('mata_pelajaran_id', $item->mata_pelajaran_id) ? '' : 'selected' }}>
                        🌐 Ujian Umum (Semua Mapel — Bank Soal gabungan)
                    </option>
                @else
                    <option value="">— Pilih —</option>
                @endif
                @foreach($mapel->pluck('nama_mapel', 'id') as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('mata_pelajaran_id', $item->mata_pelajaran_id) == $val)>{{ $lbl }}</option>
                @endforeach
            </select>
            @if($canPilihUjianUmum)
                <p class="mt-1 text-xs text-ink-500">Pilih "Ujian Umum" untuk tes yang soalnya diambil dari semua mapel sekaligus (mis. tes siswa baru / PPDB).</p>
            @endif
            @error('mata_pelajaran_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label">Metode Target <span class="text-rose-500">*</span></label>
            <select name="target_mode" class="select" x-model="mode" required>
                <option value="per_kelas">Per Kelas</option>
                <option value="per_tingkat">Per Tingkat</option>
            </select>
        </div>
        </div>

    {{-- Pilih Kelas atau Pilih Tingkat — single field tergantung mode --}}
        <div x-show="mode === 'per_kelas'" x-cloak>
        <label class="label">Pilih Kelas <span class="text-rose-500">*</span>
                <span class="text-xs text-ink-500 font-normal">(bisa pilih banyak)</span>
            </label>
            <div x-data="{ open: false }" class="relative">
                <div @click="open = !open"
                     class="input min-h-[42px] cursor-pointer flex flex-wrap gap-1.5 items-center">
                    <template x-if="selectedRombels.length === 0">
                    <span class="text-ink-500">-- Pilih Kelas --</span>
                    </template>
                    <template x-for="id in selectedRombels" :key="id">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-xs font-medium">
                            <button type="button" @click.stop="toggleRombel(id)" class="text-rose-500 hover:text-rose-700">×</button>
                            <span x-text="getRombelName(id)"></span>
                        </span>
                    </template>
                </div>
                <div x-show="open" @click.outside="open = false" x-cloak x-transition
                     class="absolute z-30 left-0 right-0 mt-1 max-h-60 overflow-y-auto card bg-white p-2 space-y-0.5">
                    <template x-for="r in rombels" :key="r.id">
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-slate-50 cursor-pointer text-sm">
                            <input type="checkbox" :value="r.id"
                                   :checked="selectedRombels.includes(r.id)"
                                   @change="toggleRombel(r.id)"
                                   class="rounded text-brand-600 focus:ring-brand-500">
                            <span x-text="r.nama"></span>
                            <span class="text-[10px] text-ink-500 ml-auto" x-text="'Tingkat ' + r.tingkat"></span>
                        </label>
                    </template>
                <template x-if="rombels.length === 0">
                    <div class="text-center text-xs text-ink-500 py-3">Belum ada rombel.</div>
                </template>
                </div>
            </div>
            <template x-for="id in selectedRombels" :key="id">
                <input type="hidden" name="rombongan_belajar_ids[]" :value="id">
            </template>
            @error('rombongan_belajar_ids')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            @foreach($errors->keys() as $key)
                @if(str_starts_with($key, 'rombongan_belajar_ids.'))
                    <p class="mt-1 text-xs text-rose-600">{{ $errors->first($key) }}</p>
                @endif
            @endforeach
        </div>

        <div x-show="mode === 'per_tingkat'" x-cloak>
        <label class="label">Pilih Tingkat <span class="text-rose-500">*</span></label>
        <select name="target_tingkat[]" class="select" :required="mode === 'per_tingkat'">
            <option value="">-- Pilih Tingkat --</option>
            @foreach($tingkatList as $tk)
                <option value="{{ $tk->nomor }}" {{ (int) $defaultTingkat === (int) $tk->nomor ? 'selected' : '' }}>
                    {{ $tk->nama }}
                </option>
            @endforeach
        </select>
            @error('target_tingkat')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        @error('target_tingkat.0')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    {{-- Tahun Ajaran + Durasi --}}
    <div class="grid md:grid-cols-2 gap-4">
        <x-field type="select" name="tahun_ajaran_id" label="Tahun Ajaran" :value="$item->tahun_ajaran_id"
                 :options="$tahunAjaran->pluck('nama_tahun_ajaran', 'id')->toArray()"/>
        <x-field name="duration" type="number" label="Durasi (menit)" :value="$item->duration ?? 30" required/>
    </div>

    {{-- Waktu --}}
    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="valid_from" type="datetime-local" label="Waktu Mulai" required
                 :value="$item->valid_from ? $item->valid_from->format('Y-m-d\\TH:i') : now()->format('Y-m-d\\TH:i')"/>
        <x-field name="valid_upto" type="datetime-local" label="Waktu Selesai" required
                 :value="$item->valid_upto ? $item->valid_upto->format('Y-m-d\\TH:i') : now()->addDay()->format('Y-m-d\\TH:i')"/>
    </div>

    {{-- Status Publish + Proteksi --}}
    <div class="grid md:grid-cols-3 gap-4">
        <x-field type="select" name="is_published" label="Status"
                 :value="(int) ($item->is_published ?? 0)"
                 :options="[0 => 'Draft', 1 => 'Publikasikan']" required/>

        <div x-data="{ showHelp: false }">
            <div class="flex items-center gap-1.5">
                <span class="label">Proteksi Ujian <span class="text-rose-500">*</span></span>

                {{-- Ikon ? untuk buka MODAL panduan --}}
                <button type="button"
                        @click="showHelp = true"
                        class="w-5 h-5 rounded-full bg-slate-200 text-slate-700 text-xs font-bold flex items-center justify-center hover:bg-brand-500 hover:text-white transition cursor-pointer"
                        aria-label="Buka panduan proteksi ujian">
                    ?
                </button>
            </div>
            <select name="proteksi_mode" class="select mt-1.5" required>
                @foreach($proteksiOptions as $val => $lbl)
                    <option value="{{ $val }}" @selected(($item->proteksi_mode ?? 'blokir') === $val)>{{ $lbl }}</option>
                @endforeach
            </select>

            {{-- MODAL PANDUAN PROTEKSI UJIAN --}}
            <div x-show="showHelp" x-cloak
                 class="fixed inset-0 z-50 grid place-items-center sm:p-4 p-3 backdrop-blur-sm"
                 style="background: rgba(15, 23, 42, 0.5);"
                 x-transition.opacity
                 @keydown.escape.window="showHelp = false">
                <div @click.outside="showHelp = false"
                     class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[92vh] flex flex-col overflow-hidden ring-1 ring-slate-200/60"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95">

                    {{-- HEADER --}}
                    <div class="relative px-5 sm:px-6 py-4 bg-gradient-to-br from-brand-600 to-brand-700 text-white overflow-hidden">
                        <div class="absolute -top-10 -right-6 w-32 h-32 rounded-full bg-white/10 blur-3xl"></div>
                        <div class="absolute -bottom-14 -left-6 w-32 h-32 rounded-full bg-accent-400/20 blur-3xl"></div>
                        <div class="relative flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 shrink-0 rounded-xl bg-white/15 backdrop-blur grid place-items-center text-[22px] shadow-inner ring-1 ring-white/10">
                                    🛡️
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-bold text-[15px] leading-tight tracking-tight">Panduan Proteksi Ujian</h3>
                                    <p class="text-[11px] text-white/75 mt-1 leading-snug">4 mode tindakan saat siswa melanggar</p>
                                </div>
                            </div>
                            <button type="button" @click="showHelp = false"
                                    class="w-8 h-8 shrink-0 rounded-lg bg-white/10 hover:bg-white/20 transition-all grid place-items-center text-white text-lg leading-none hover:rotate-90 duration-200"
                                    aria-label="Tutup">×</button>
                        </div>
                    </div>

                    {{-- BODY --}}
                    <div class="px-4 py-3.5 overflow-y-auto space-y-2">

                        {{-- Card 1: Logout Otomatis --}}
                        <div class="group relative rounded-xl border border-emerald-200/70 bg-gradient-to-br from-emerald-50/70 to-white p-3 hover:border-emerald-300 hover:shadow-soft transition-all">
                            <div class="absolute left-0 top-3 bottom-3 w-1 rounded-r bg-emerald-500"></div>
                            <div class="flex items-start gap-3 pl-1.5">
                                <div class="w-9 h-9 shrink-0 rounded-lg bg-gradient-to-br from-emerald-100 to-emerald-200 text-emerald-700 grid place-items-center text-base shadow-sm group-hover:scale-110 transition-transform">🚪</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <h4 class="font-bold text-emerald-800 text-[12px]">Logout Otomatis</h4>
                                        <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-emerald-500/15 text-emerald-700 font-semibold"></span>
                                    </div>
                                    <p class="text-[11px] text-ink-700 mt-1 leading-snug">
                                        Jawaban di-submit paksa, siswa keluar dari ujian. <strong class="text-emerald-700">Masih bisa</strong> ikut lagi.
                                    </p>
                                    <div class="mt-1.5 text-[10px] text-emerald-700 font-medium flex items-center gap-1">
                                        <span class="w-3.5 h-3.5 rounded-full bg-emerald-500 text-white grid place-items-center text-[8px]">✓</span>
                                        <span>Latihan / harian</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Card 2: Blokir Otomatis (REKOMENDASI) --}}
                        <div class="group relative rounded-xl border-2 border-rose-300 bg-gradient-to-br from-rose-50/80 to-white p-3 hover:shadow-soft transition-all ring-1 ring-rose-100">
                            <div class="absolute left-0 top-3 bottom-3 w-1 rounded-r bg-rose-500"></div>
                            <div class="absolute -top-2 right-3 text-[8px] px-2 py-0.5 rounded-full bg-gradient-to-r from-rose-600 to-rose-500 text-white font-bold uppercase tracking-widest shadow-md animate-pulse">
                                
                            </div>
                            <div class="flex items-start gap-3 pl-1.5">
                                <div class="w-9 h-9 shrink-0 rounded-lg bg-gradient-to-br from-rose-100 to-rose-200 text-rose-700 grid place-items-center text-base shadow-sm group-hover:scale-110 transition-transform">🔒</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <h4 class="font-bold text-rose-800 text-[13px]">Blokir Otomatis</h4>
                                        <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-rose-500/15 text-rose-700 font-semibold"></span>
                                    </div>
                                    <p class="text-[11px] text-ink-700 mt-1 leading-snug">
                                        Jawaban di-submit paksa & attempt <strong class="text-rose-700">dikunci</strong>. Hanya admin yang bisa buka.
                                    </p>
                                    <div class="mt-1.5 text-[10px] text-rose-700 font-medium flex items-center gap-1">
                                        <span class="w-3.5 h-3.5 rounded-full bg-rose-500 text-white grid place-items-center text-[8px]">✓</span>
                                        <span>UTS / UAS / ASTS</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Card 3: Peringatan Saja --}}
                        <div class="group relative rounded-xl border border-amber-200/70 bg-gradient-to-br from-amber-50/70 to-white p-3 hover:border-amber-300 hover:shadow-soft transition-all">
                            <div class="absolute left-0 top-3 bottom-3 w-1 rounded-r bg-amber-500"></div>
                            <div class="flex items-start gap-3 pl-1.5">
                                <div class="w-9 h-9 shrink-0 rounded-lg bg-gradient-to-br from-amber-100 to-amber-200 text-amber-700 grid place-items-center text-base shadow-sm group-hover:scale-110 transition-transform">⚠️</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <h4 class="font-bold text-amber-800 text-[13px]">Peringatan Saja</h4>
                                        <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-amber-500/15 text-amber-700 font-semibold"></span>
                                    </div>
                                    <p class="text-[11px] text-ink-700 mt-1 leading-snug">
                                        Pelanggaran dicatat ke log, <strong class="text-amber-700">tidak ada aksi otomatis</strong>.
                                    </p>
                                    <div class="mt-1.5 text-[10px] text-amber-700 font-medium flex items-center gap-1">
                                        <span class="w-3.5 h-3.5 rounded-full bg-amber-500 text-white grid place-items-center text-[8px]">✓</span>
                                        <span>Simulasi / try-out</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Card 4: Off --}}
                        <div class="group relative rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50/70 to-white p-3 hover:border-slate-300 hover:shadow-soft transition-all">
                            <div class="absolute left-0 top-3 bottom-3 w-1 rounded-r bg-slate-400"></div>
                            <div class="flex items-start gap-3 pl-1.5">
                                <div class="w-9 h-9 shrink-0 rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 text-slate-600 grid place-items-center text-base shadow-sm group-hover:scale-110 transition-transform">⚪</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <h4 class="font-bold text-slate-700 text-[13px]">Off</h4>
                                        <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-slate-500/15 text-slate-600 font-semibold"></span>
                                    </div>
                                    <p class="text-[11px] text-ink-700 mt-1 leading-snug">
                                        Anti-cheat <strong class="text-slate-700">dimatikan total</strong>.
                                    </p>
                                    <div class="mt-1.5 text-[10px] text-slate-600 font-medium flex items-center gap-1">
                                        <span class="w-3.5 h-3.5 rounded-full bg-slate-400 text-white grid place-items-center text-[8px]">✓</span>
                                        <span>Latihan bebas</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Info tip --}}
                        <div class="mt-1.5 rounded-lg bg-gradient-to-r from-brand-50 to-accent-50/50 border border-brand-100 px-3 py-2 flex items-start gap-2">
                            <div class="w-5 h-5 shrink-0 rounded-full bg-brand-100 grid place-items-center text-[10px]">💡</div>
                            <p class="text-[10px] text-brand-800 leading-snug">
                                Batas pelanggaran diatur di field <strong>Max Pelanggaran</strong>.
                            </p>
                        </div>
                    </div>

                    {{-- FOOTER --}}
                    <div class="px-4 py-2.5 border-t border-slate-100 bg-gradient-to-b from-white to-slate-50/60 flex items-center justify-between gap-2">
                        <span class="text-[10px] text-ink-500 hidden sm:flex items-center gap-1">
                            <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
                            Pilih sesuai jenis ujian
                        </span>
                        <button type="button" @click="showHelp = false"
                                class="btn-primary text-xs px-4 py-1.5 sm:ml-auto w-full sm:w-auto justify-center shadow-md hover:shadow-lg transition-shadow">
                            Mengerti
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <x-field name="max_violations" type="number" label="Max Pelanggaran" :value="$item->max_violations ?? 3"
                 help="Jumlah pelanggaran sebelum aksi proteksi dijalankan"/>
    </div>

    {{-- Pengacakan --}}
    <div class="pt-3 border-t border-slate-100">
        <div class="flex items-center gap-2 text-sm font-semibold text-ink-900 mb-3">
            <span>Pengacakan</span>
        </div>
    <div class="grid md:grid-cols-3 gap-4">
        <x-field type="select" name="randomize" label="Acak Urutan Soal"
                 :value="(int)($item->randomize ?? 0)"
                 :options="[0 => 'Tidak', 1 => 'Ya']" required/>
        <x-field type="select" name="randomize_options" label="Acak Opsi Jawaban"
                 :value="(int)($item->randomize_options ?? 0)"
                 :options="[0 => 'Tidak', 1 => 'Ya']" required/>
        <x-field type="select" name="show_score" label="Tampilkan Nilai"
                 :value="(int)($item->show_score ?? 1)"
                 :options="[0 => 'Tidak', 1 => 'Ya']" required/>
    </div>
    </div>

    {{-- Token Sesi --}}
    <div class="pt-3 border-t border-slate-100" x-data="{ requireToken: {{ (int) ($item->require_session_token ?? 0) }} }">
        <div class="flex items-center gap-2 text-sm font-semibold text-ink-900 mb-3">
            <span> Token Sesi</span>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="label">Wajibkan Token Sesi</label>
                <select name="require_session_token" class="select mt-1.5" x-model.number="requireToken">
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
                <p class="text-xs text-ink-500 mt-1">Kalau "Ya", siswa wajib memasukkan token sesi yang benar sebelum bisa memulai ujian ini.</p>
            </div>

            <div x-show="requireToken === 1" x-cloak>
                <label class="label">Pilih Token Sesi <span class="text-rose-500">*</span></label>
                <select name="session_token_id" class="select mt-1.5" :required="requireToken === 1">
                    <option value="">-- Pilih Token --</option>
                    @foreach($sessionTokens as $st)
                        <option value="{{ $st->id }}" @selected(($item->session_token_id ?? null) == $st->id)>
                            {{ $st->token }}@if($st->nama_sesi) — {{ $st->nama_sesi }}@endif@if(! $st->is_active) (nonaktif)@endif
                        </option>
                    @endforeach
                </select>
                @error('session_token_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                <p class="text-xs text-ink-500 mt-1">
                    Belum ada token yang cocok?
                    <a href="{{ route('token-sesi.create') }}" class="text-brand-600 hover:underline" target="_blank">Buat token sesi baru</a>.
                </p>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('tes.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">{{ $item->exists ? 'Simpan' : 'Lanjut: Pilih Soal' }}</button>
    </div>
</form>

<script>
function tesForm(cfg) {
    return {
        mode: cfg.mode,
        selectedRombels: (cfg.selectedRombels || []).map(Number),
        rombels: cfg.rombels,

        toggleRombel(id) {
            id = Number(id);
            const i = this.selectedRombels.indexOf(id);
            if (i >= 0) this.selectedRombels.splice(i, 1);
            else this.selectedRombels.push(id);
        },
        getRombelName(id) {
            const r = this.rombels.find(x => x.id === Number(id));
            return r ? r.nama : '?';
        },
    };
}
window.tesForm = tesForm;
</script>
@endsection
