@extends('layouts.app')
@section('title', 'Monitoring Ujian Siswa')
@section('breadcrumb', 'Admin / Monitoring Ujian')

@push('head')
<meta http-equiv="refresh" content="30">  {{-- auto-refresh tiap 30 detik --}}
@endpush

@section('content')
<div class="card overflow-hidden">
    {{-- Header bar --}}
    <div class="px-6 py-4 bg-gradient-to-r from-brand-700 to-violet-700 text-white flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-white/20 grid place-items-center">
                <x-icon name="clock" class="w-5 h-5"/>
            </div>
            <h2 class="text-lg font-bold">Monitoring Ujian Siswa</h2>
        </div>
    </div>

    {{-- Filter bar --}}
    <form method="GET" class="grid md:grid-cols-4 gap-3 p-5 bg-slate-50/60 border-b border-slate-100">
        <div>
            <label class="label">Kelas</label>
            <select name="rombel" class="select" onchange="this.form.submit()">
                <option value="">Pilih Kelas</option>
                <optgroup label="Per Tingkat">
                    @foreach($tingkats as $t)
                        <option value="t{{ $t }}" @selected(request('rombel')==='t'.$t)>Tingkat {{ $t }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="Per Rombel">
                    @foreach($rombels as $r)
                        <option value="{{ $r->id }}" @selected(request('rombel')==$r->id)>{{ $r->nama_rombel }}</option>
                    @endforeach
                </optgroup>
            </select>
        </div>
        <div>
            <label class="label">Mata Pelajaran</label>
            <select name="mapel" class="select" onchange="this.form.submit()">
                <option value="">Pilih Mata Pelajaran</option>
                <option value="umum" @selected(request('mapel')==='umum')>🌐 Ujian Umum (tanpa mapel)</option>
                @foreach($mapels as $m)
                    <option value="{{ $m->id }}" @selected(request('mapel')==$m->id)>{{ $m->nama_mapel }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Status</label>
            <select name="status" class="select" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="menunggu"    @selected(request('status')==='menunggu')>Menunggu</option>
                <option value="berlangsung" @selected(request('status')==='berlangsung')>Berlangsung</option>
                <option value="selesai"     @selected(request('status')==='selesai')>Selesai</option>
                <option value="draft"       @selected(request('status')==='draft')>Draft</option>
            </select>
        </div>
        <div>
            <label class="label">Tanggal</label>
            <input type="date" name="tanggal" value="{{ request('tanggal') }}" class="input" onchange="this.form.submit()">
        </div>
    </form>

    {{-- Tabel agregat --}}
    <div class="px-5 pt-4 flex items-center justify-between text-sm text-ink-600">
        <div>Menampilkan <strong>{{ $items->count() }}</strong> dari <strong>{{ $items->total() }}</strong> ujian</div>
        <a href="{{ route('monitoring.index') }}" class="text-brand-600 hover:underline text-xs">↻ Reset filter</a>
    </div>

    <div class="overflow-x-auto px-5 pb-5 mt-2">
        <table class="table-modern">
            <thead><tr>
                <th class="text-center w-12">No.</th>
                <th>Judul</th>
                <th>Kelas</th>
                <th>Mata Pelajaran</th>
                <th>Waktu</th>
                <th class="text-center">Status</th>
                <th class="text-center">Total Peserta</th>
                <th class="text-center">Berhasil Mengerjakan</th>
                <th class="text-center">Selesai Mengerjakan</th>
                <th class="text-center">Pelanggar</th>
                <th class="text-center">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse($items as $idx => $q)
                <tr>
                    <td class="text-center font-semibold text-ink-500">{{ $items->firstItem() + $idx }}.</td>
                    <td class="font-semibold text-ink-900 max-w-[200px]">{{ $q->name }}</td>
                    <td>
                        @if($q->target_mode === 'per_tingkat' && ! empty($q->target_tingkat))
                            @foreach((array) $q->target_tingkat as $tk)
                                <span class="badge-info">Tingkat {{ $tk }}</span>
                            @endforeach
                        @elseif($q->rombelTargets->isNotEmpty())
                            @foreach($q->rombelTargets as $rb)
                                <span class="badge-info">{{ $rb->nama_rombel }}</span>
                            @endforeach
                        @elseif($q->rombel)
                            <span class="badge-info">{{ $q->rombel->nama_rombel }}</span>
                        @else
                            <span class="badge-muted">Semua</span>
                        @endif
                    </td>
                    <td>{{ optional($q->mapel)->nama_mapel ?? '🌐 Ujian Umum' }}</td>
                    <td class="text-xs whitespace-nowrap">
                        {{ optional($q->valid_from)->format('H:i') }} ({{ optional($q->valid_from)->translatedFormat('d M Y') }})<br>
                        {{ optional($q->valid_upto)->format('H:i') }} ({{ optional($q->valid_upto)->translatedFormat('d M Y') }})
                    </td>
                    <td class="text-center">
                        <span class="{{ $q->status_badge }}">{{ ucfirst($q->status) }}</span>
                    </td>
                    <td class="text-center font-bold text-ink-900">{{ $q->total_peserta }}</td>
                    <td class="text-center">
                        <span class="font-bold text-sky-700">{{ $q->total_mulai }}</span>
                        <div class="text-[10px] text-ink-500">
                            {{ $q->total_peserta > 0 ? round($q->total_mulai / $q->total_peserta * 100) : 0 }}%
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="font-bold text-emerald-700">{{ $q->total_selesai }}</span>
                        <div class="text-[10px] text-ink-500">
                            {{ $q->total_peserta > 0 ? round($q->total_selesai / $q->total_peserta * 100) : 0 }}%
                        </div>
                    </td>
                    <td class="text-center">
                        @if(($q->total_pelanggar ?? 0) > 0)
                            <span class="badge-danger">⚠ {{ $q->total_pelanggar }}</span>
                        @else
                            <span class="text-ink-400">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <a href="{{ route('monitoring.detail', $q) }}"
                           class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-sky-100 text-sky-700 hover:bg-sky-200 transition"
                           title="Lihat detail peserta">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
  <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
  <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
</svg>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center py-12 text-ink-500">
                    <div class="text-3xl mb-2">📭</div>
                    <div>Tidak ada ujian yang cocok dengan filter</div>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-5 pb-5">{{ $items->links() }}</div>
</div>

<div class="mt-4 text-xs text-ink-500 text-center">
    🔄 Halaman ini auto-refresh setiap 30 detik
</div>
@endsection
