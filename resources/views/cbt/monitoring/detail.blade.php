@extends('layouts.app')
@section('title', 'Detail: '.$quiz->name)
@section('breadcrumb', 'Admin / Monitoring / Detail')

@push('head')
<meta http-equiv="refresh" content="15">  {{-- detail = 15 detik --}}
@endpush

@section('content')
<x-page-header :title="$quiz->name"
               :subtitle="(optional($quiz->mapel)->nama_mapel ?? '-').' • '.(optional($quiz->rombel)->nama_rombel ?? 'Semua kelas')">
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

<div class="card overflow-x-auto">
    <table class="table-modern">
        <thead><tr>
            <th class="w-12 text-center">No.</th>
            <th>Siswa</th>
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
                        <img src="{{ $s->profile_photo_url }}" class="w-8 h-8 rounded-full object-cover">
                        <div>
                            <div class="font-semibold text-ink-900">{{ $s->nama_siswa }}</div>
                            <div class="text-[10px] text-ink-500">{{ $s->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</div>
                        </div>
                    </div>
                </td>
                <td class="font-mono text-xs">{{ $s->nisn }}</td>
                <td class="text-center">
                    @if(! $a)
                        <span class="badge-muted">Belum</span>
                    @else
                        <span class="{{ $a->status_badge }}">{{ ucfirst($a->status) }}</span>
                    @endif
                </td>
                <td class="text-center">
                    @if($a)
                        <span class="font-bold {{ $a->violation_count >= 3 ? 'text-rose-600' : ($a->violation_count > 0 ? 'text-amber-600' : 'text-emerald-600') }}">
                            {{ $a->violation_count }}
                        </span>
                    @else
                        <span class="text-ink-400">—</span>
                    @endif
                </td>
                <td class="text-right font-bold text-brand-600">
                    {{ $a && $a->score !== null ? number_format($a->score, 1) : '—' }}
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
            <tr><td colspan="8" class="text-center py-10 text-ink-500">Tidak ada peserta.</td></tr>
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
    <br>🔄 Halaman ini auto-refresh setiap 15 detik
</div>
@endsection
