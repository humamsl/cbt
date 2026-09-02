@extends('layouts.app')
@section('title', 'Detail: '.$quiz->name)
@section('breadcrumb', 'Admin / Monitoring / Detail')

@push('head')
<script>
// Auto-refresh 15 detik, TAPI ditunda kalau modal riwayat kecurangan sedang
// terbuka -- meta-refresh biasa akan menutup paksa modal itu di tengah admin
// membacanya. window.__pauseAutoRefresh di-toggle dari x-effect di bawah.
(function () {
    function scheduleReload() {
        setTimeout(function () {
            if (window.__pauseAutoRefresh) { scheduleReload(); return; }
            location.reload();
        }, 15000);
    }
    scheduleReload();
})();
</script>
@endpush

@section('content')
@php
    $targetLabel = $quiz->target_mode === 'per_tingkat'
        ? 'Tingkat '.implode(', ', (array) ($quiz->target_tingkat ?? []))
        : ($targetRombels->pluck('nama_rombel')->implode(', ') ?: 'Semua kelas');
@endphp
<x-page-header :title="$quiz->name"
               :subtitle="(optional($quiz->mapel)->nama_mapel ?? '-').' • '.$targetLabel">
    <x-slot:action>
        <a href="{{ route('monitoring.index') }}" class="btn-secondary">← Kembali</a>
    </x-slot:action>
</x-page-header>

@php
    $totalPeserta = $siswas->count();
    $mulai = $attempts->filter(fn ($a) => $a->time_start !== null)->count();
    $selesai = $attempts->filter(fn ($a) => $a->is_done)->count();
    $blokir = $attempts->filter(fn ($a) => $a->is_blocked)->count();
    $belum = $totalPeserta - $attempts->count();
    $pelanggar = $attempts->filter(fn ($a) => ($a->violation_count ?? 0) > 0)->count();
@endphp

<div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-5">
    <x-stat-card label="Total Peserta"        :value="$totalPeserta" icon="users"    tone="brand"/>
    <x-stat-card label="Berhasil Mengerjakan" :value="$mulai"        icon="check"    tone="sky"/>
    <x-stat-card label="Selesai"              :value="$selesai"      icon="document" tone="emerald"/>
    <x-stat-card label="Terdeteksi Curang"    :value="$pelanggar"    icon="trash"    tone="amber"/>
    <x-stat-card label="Terblokir"            :value="$blokir"       icon="trash"    tone="rose"/>
    <x-stat-card label="Belum Mengerjakan"    :value="$belum"        icon="clock"    tone="amber"/>
</div>

{{-- FILTER: per kelas + cari siswa --}}
<form method="GET" class="card card-pad mb-4 flex flex-wrap items-end gap-2">
    <div>
        <label class="label text-xs">Kelas</label>
        <select name="rombel" class="select min-w-[180px]" onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            @foreach($targetRombels as $rb)
                <option value="{{ $rb->id }}" @selected($rombelFilter === $rb->id)>{{ $rb->nama_rombel }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex-1 min-w-[200px]">
        <label class="label text-xs">Cari Siswa</label>
        <input name="q" value="{{ $search }}" class="input w-full" placeholder="Nama siswa atau NISN...">
    </div>
    <button class="btn-primary"><x-icon name="search" class="w-4 h-4"/> Cari</button>
    @if($rombelFilter || $search !== '')
        <a href="{{ route('monitoring.detail', $quiz) }}" class="btn-secondary">Reset</a>
    @endif
</form>

<div x-data="{ openViolations: null }" x-effect="window.__pauseAutoRefresh = openViolations !== null">
{{-- KARTU (HP/tablet, < lg): tabel 9 kolom di bawah kalau digulir horizontal
     membuat kolom Aksi (tombol lihat/blokir/reset) nyaris tidak terjangkau. --}}
<div class="lg:hidden grid gap-3">
    @foreach($siswas as $i => $s)
        @php $a = $attempts[$s->id] ?? null; @endphp
        <div class="card card-pad">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div class="flex items-center gap-2 min-w-0">
                    <x-avatar :src="$s->profile_photo_url" :name="$s->nama_siswa" size="w-9 h-9"/>
                    <div class="min-w-0">
                        <div class="font-semibold text-ink-900 truncate">{{ $s->nama_siswa }}</div>
                        <div class="text-[11px] text-ink-500">{{ $s->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }} &middot; {{ $s->nisn }}</div>
                    </div>
                </div>
                <span class="badge-info shrink-0">{{ $s->nama_kelas }}</span>
            </div>

            <div class="flex items-center flex-wrap gap-2 mb-3">
                @if(! $a)
                    <span class="badge-muted">Belum</span>
                @else
                    <span class="{{ $a->status_badge }}">{{ ucfirst($a->status) }}</span>
                @endif

                @if($a && $a->violation_count > 0)
                    <button type="button" @click="openViolations = {{ $a->id }}"
                            class="text-xs font-bold underline decoration-dotted underline-offset-2 {{ $a->violation_count >= 3 ? 'text-rose-600' : 'text-amber-600' }}">
                        ⚠ {{ $a->violation_count }} pelanggaran
                    </button>
                @endif

                @if($a && $a->nilai !== null)
                    <span class="text-xs font-bold text-brand-600 ml-auto">Nilai: {{ number_format($a->nilai, 1) }}</span>
                @endif
            </div>

            @if($a)
                <div class="text-xs text-ink-500 mb-3">
                    Mulai {{ optional($a->time_start)->format('d/m H:i') ?: '—' }} &middot;
                    Selesai {{ optional($a->time_end)->format('d/m H:i') ?: '—' }}
                </div>

                <div class="flex items-center gap-1.5 flex-wrap">
                    <a href="{{ route('monitoring.lihat', $a) }}" class="btn-secondary text-xs">
                        <x-icon name="document" class="w-4 h-4"/> Lihat Jawaban
                    </a>

                    @if(! $a->is_blocked && ! $a->is_done)
                        <form method="POST" action="{{ route('monitoring.block', $a) }}"
                              onsubmit="return confirm('Blokir ujian siswa ini?')">
                            @csrf
                            <button class="btn-secondary text-xs text-rose-600">
                                <x-icon name="trash" class="w-4 h-4"/> Blokir
                            </button>
                        </form>
                    @endif

                    @if($a->is_blocked)
                        <form method="POST" action="{{ route('monitoring.unblock', $a) }}"
                              onsubmit="return confirm('Buka blokir? Jawaban & pelanggaran dipertahankan.')">
                            @csrf
                            <button class="btn-secondary text-xs text-emerald-600">
                                <x-icon name="check" class="w-4 h-4"/> Buka Blokir
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('monitoring.reset', $a) }}"
                          onsubmit="return confirm('Aktifkan ujian ulang? Jawaban & pelanggaran akan dihapus.')">
                        @csrf @method('DELETE')
                        <button class="btn-secondary text-xs text-amber-600">
                            <x-icon name="clock" class="w-4 h-4"/> Reset
                        </button>
                    </form>
                </div>
            @else
                <div class="text-xs text-ink-400">Belum ujian</div>
            @endif
        </div>
    @endforeach
    @if($siswas->isEmpty())
        <div class="card card-pad text-center py-10 text-ink-500">
            Tidak ada peserta{{ $search !== '' ? ' yang cocok dengan pencarian "'.$search.'"' : '' }}.
        </div>
    @endif
</div>

{{-- TABEL (lg ke atas) --}}
<div class="hidden lg:block card table-wrap">
    <table class="table-modern">
        <thead><tr>
            <th class="w-12 text-center">No.</th>
            <th>Siswa</th>
            <th>Kelas</th>
            <th>NISN</th>
            <th class="text-center">Status</th>
            <th class="text-center">Pelanggaran</th>
            <th class="text-right">Nilai</th>
            <th>Mulai / Selesai</th>
            <th class="text-center">Aksi</th>
        </tr></thead>
        <tbody>
        @foreach($siswas as $i => $s)
            @php $a = $attempts[$s->id] ?? null; @endphp
            <tr>
                <td class="text-center text-ink-500">{{ $i + 1 }}</td>
                <td>
                    <div class="flex items-center gap-2">
                        <x-avatar :src="$s->profile_photo_url" :name="$s->nama_siswa" size="w-8 h-8"/>
                        <div>
                            <div class="font-semibold text-ink-900">{{ $s->nama_siswa }}</div>
                            <div class="text-[10px] text-ink-500">{{ $s->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</div>
                        </div>
                    </div>
                </td>
                <td><span class="badge-info">{{ $s->nama_kelas }}</span></td>
                <td class="font-mono text-xs">{{ $s->nisn }}</td>
                <td class="text-center">
                    @if(! $a)
                        <span class="badge-muted">Belum</span>
                    @else
                        <span class="{{ $a->status_badge }}">{{ ucfirst($a->status) }}</span>
                    @endif
                </td>
                <td class="text-center">
                    @if($a && $a->violation_count > 0)
                        <button type="button" @click="openViolations = {{ $a->id }}"
                                title="Lihat riwayat kecurangan"
                                class="font-bold underline decoration-dotted underline-offset-2 hover:opacity-70 {{ $a->violation_count >= 3 ? 'text-rose-600' : 'text-amber-600' }}">
                            {{ $a->violation_count }}
                        </button>
                    @elseif($a)
                        <span class="font-bold text-emerald-600">0</span>
                    @else
                        <span class="text-ink-400">—</span>
                    @endif
                </td>
                <td class="text-right font-bold text-brand-600">
                    {{ $a && $a->nilai !== null ? number_format($a->nilai, 1) : '—' }}
                </td>
                <td class="text-xs text-ink-500 whitespace-nowrap">
                    {{ optional($a?->time_start)->format('d/m H:i') ?: '—' }}<br>
                    {{ optional($a?->time_end)->format('d/m H:i') ?: '—' }}
                </td>
                <td>
                    <div class="flex items-center justify-center gap-1">
                        @if($a)
                            {{-- LIHAT --}}
                            <a href="{{ route('monitoring.lihat', $a) }}" title="Lihat detail jawaban"
                               class="btn-ghost p-1.5">
                                <x-icon name="document" class="w-4 h-4"/>
                            </a>

                            {{-- BLOKIR --}}
                            @if(! $a->is_blocked && ! $a->is_done)
                                <form method="POST" action="{{ route('monitoring.block', $a) }}"
                                      onsubmit="return confirm('Blokir ujian siswa ini?')">
                                    @csrf
                                    <button class="btn-ghost p-1.5 text-rose-600" title="Blokir ujian">
                                        <x-icon name="trash" class="w-4 h-4"/>
                                    </button>
                                </form>
                            @endif

                            {{-- BUKA BLOKIR --}}
                            @if($a->is_blocked)
                                <form method="POST" action="{{ route('monitoring.unblock', $a) }}"
                                      onsubmit="return confirm('Buka blokir? Jawaban & pelanggaran dipertahankan.')">
                                    @csrf
                                    <button class="btn-ghost p-1.5 text-emerald-600" title="Buka blokir">
                                        <x-icon name="check" class="w-4 h-4"/>
                                    </button>
                                </form>
                            @endif

                            {{-- RESET UJIAN ULANG --}}
                            <form method="POST" action="{{ route('monitoring.reset', $a) }}"
                                  onsubmit="return confirm('Aktifkan ujian ulang? Jawaban & pelanggaran akan dihapus.')">
                                @csrf @method('DELETE')
                                <button class="btn-ghost p-1.5 text-amber-600" title="Reset / aktifkan ujian ulang">
                                    <x-icon name="clock" class="w-4 h-4"/>
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-ink-400">Belum ujian</span>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        @if($siswas->isEmpty())
            <tr><td colspan="9" class="text-center py-10 text-ink-500">Tidak ada peserta{{ $search !== '' ? ' yang cocok dengan pencarian "'.$search.'"' : '' }}.</td></tr>
        @endif
        </tbody>
    </table>
</div>

<div class="mt-4 text-xs text-ink-500 text-center">
    Legend aksi:
    <span class="text-rose-600 ml-2">Blokir</span> ·
    <span class="text-emerald-600 ml-2">Buka Blokir</span> ·
    <span class="text-amber-600 ml-2">Reset / Ujian Ulang</span> ·
    <span class="text-sky-600 ml-2">Lihat Jawaban</span>
    <br>🔄 Halaman ini auto-refresh setiap 15 detik. Klik angka di kolom
    <strong>Pelanggaran</strong> untuk lihat riwayat kecurangan.
</div>

{{-- ====================== MODAL: RIWAYAT KECURANGAN ======================
     Satu modal per siswa yang punya pelanggaran (>0) -- tersembunyi via
     x-show, konten sudah dirender server-side (bukan fetch AJAX) supaya
     tetap sederhana untuk jumlah peserta per halaman ini (maks 15/rombel). --}}
@foreach($siswas as $s)
    @php $a = $attempts[$s->id] ?? null; @endphp
    @if($a && $a->violation_count > 0)
        <div x-show="openViolations === {{ $a->id }}" x-cloak
             class="fixed inset-0 z-50 bg-ink-900/60 backdrop-blur-sm grid place-items-center p-4"
             @keydown.escape.window="openViolations = null">
            <div @click.outside="openViolations = null"
                 class="card max-w-lg w-full max-h-[85vh] flex flex-col overflow-hidden p-0">
                <div class="p-4 border-b border-slate-100 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-bold text-ink-900 truncate">{{ $s->nama_siswa }}</div>
                        <div class="text-xs text-ink-500">Riwayat Kecurangan — {{ $a->violation_count }} pelanggaran tercatat</div>
                    </div>
                    <button type="button" @click="openViolations = null"
                            class="text-ink-400 hover:text-ink-900 text-xl leading-none shrink-0">×</button>
                </div>

                @php $k = $a->konsekuensi_pelanggaran; @endphp
                @if($k)
                    <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
                        <span class="{{ $k['badge'] }}">{{ $k['text'] }}</span>
                        @if($k['detail'])
                            <div class="text-xs text-ink-500 mt-1">{{ $k['detail'] }}</div>
                        @endif
                    </div>
                @endif

                <div class="overflow-y-auto p-4 space-y-2.5">
                    @forelse($a->violations as $v)
                        <div class="flex items-start gap-3 text-sm pb-2.5 border-b border-slate-50 last:border-0 last:pb-0">
                            <span class="text-[11px] text-ink-400 font-mono w-[70px] shrink-0 pt-0.5">{{ $v->created_at->format('H:i:s') }}</span>
                            <div class="min-w-0">
                                <div class="font-medium text-ink-800">{{ $v->label }}</div>
                                @if($v->detail)<div class="text-xs text-ink-500 break-words">{{ $v->detail }}</div>@endif
                                @if($v->ip_address)<div class="text-[10px] text-ink-400 mt-0.5">IP: {{ $v->ip_address }}</div>@endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-ink-500 text-center py-4">Belum ada rincian tercatat.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
@endforeach
</div>
@endsection
