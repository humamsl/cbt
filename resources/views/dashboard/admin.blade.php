@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Beranda / Dashboard')

@section('content')
<div class="space-y-6">

    {{-- Greeting banner --}}
    <div class="rounded-3xl p-6 md:p-8 relative overflow-hidden text-white shadow-glow"
         style="background: linear-gradient(135deg, #500596 0%, #00c2ae 45%, #500596 100%);">
        <div class="absolute -top-16 -right-16 w-72 h-72 rounded-full bg-white/10 blur-3xl"></div>
        <div class="absolute -bottom-20 -left-10 w-72 h-72 rounded-full bg-white/5 blur-3xl"></div>
        <div class="relative z-10 flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="text-brand-100 text-sm">{{ now()->translatedFormat('l, d F Y') }}</div>
                <h2 class="text-2xl md:text-3xl font-bold mt-1">Halo, {{ auth()->user()->name ?? 'Admin' }} </h2>
                <p class="text-brand-100 mt-1 text-sm">Selamat datang kembali di dashboard {{ $AppCfg['app_name'] }}.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('tes.create') }}" class="bg-white/15 hover:bg-white/25 backdrop-blur px-4 py-2 rounded-xl text-sm font-semibold transition border border-white/20">
                    + Registrasi Ujian
                </a>
                <a href="{{ route('bank-soal.create') }}" class="bg-white text-brand-700 hover:bg-brand-50 px-4 py-2 rounded-xl text-sm font-semibold transition">
                    + Buat Soal
                </a>
            </div>
        </div>
    </div>

    @php $dcUrl = rtrim(config('services.datacenter.app_url'), '/'); @endphp

    {{-- Stat cards row 1 (data induk — dikelola & di-link ke aplikasi Data Center) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-stat-card label="Total Siswa" :value="number_format($stats['siswa'])" icon="users" tone="brand" href="{{ $dcUrl }}/siswa"/>
        <x-stat-card label="Total Guru" :value="number_format($stats['guru'])" icon="user-tie" tone="emerald" href="{{ $dcUrl }}/guru"/>
        <x-stat-card label="Mata Pelajaran" :value="$stats['mapel']" icon="book" tone="sky" href="{{ $dcUrl }}/mapel"/>
        <x-stat-card label="Rombongan Belajar" :value="$stats['rombel']" icon="grid" tone="amber" href="{{ $dcUrl }}/rombel"/>
    </div>

    {{-- Stat cards row 2 --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-stat-card label="Bank Soal" :value="number_format($stats['soal'])" icon="document" tone="violet" href="{{ route('bank-soal.index') }}"/>
        <x-stat-card label="Total Registrasi Ujian" :value="number_format($stats['tes'])" icon="clipboard" tone="emerald" href="{{ route('tes.index') }}"/>
        <x-stat-card label="Sedang Ujian" :value="$stats['sedang_ujian']" icon="clock" tone="rose"/>
        <x-stat-card label="Attempt Hari Ini" :value="$stats['attempt_hari_ini']" icon="chart" tone="sky"/>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- Ujian aktif --}}
        <div class="card lg:col-span-2 fade-in-up">
            <div class="card-header">
                <div>
                    <h3 class="text-base font-semibold text-ink-900">Ujian Aktif</h3>
                    <p class="text-xs text-ink-500">Registrasi ujian yang sedang dipublikasikan</p>
                </div>
                <a href="{{ route('tes.index') }}" class="btn-secondary text-xs py-1.5 px-3">Lihat semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table-modern">
                    <thead><tr>
                        <th>Nama Ujian</th><th>Mapel</th><th>Kelas</th><th>Periode</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                    @forelse($ujianAktif as $q)
                        <tr>
                            <td class="font-semibold text-ink-900">{{ $q->name }}</td>
                            <td>{{ optional($q->mapel)->nama_mapel ?? '-' }}</td>
                            <td>{{ optional($q->rombel)->nama_rombel ?? '-' }}</td>
                            <td class="text-xs text-ink-500">
                                {{ optional($q->valid_from)->format('d M H:i') }} &mdash; {{ optional($q->valid_upto)->format('d M H:i') }}
                            </td>
                            <td><span class="{{ $q->status_badge }}">{{ ucfirst($q->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-ink-500 py-10">
                            <div class="text-3xl mb-2">📋</div>
                            <div>Belum ada ujian aktif</div>
                            <a href="{{ route('tes.create') }}" class="text-brand-600 text-xs hover:underline mt-1 inline-block">Buat tes pertama</a>
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Hasil terbaru --}}
        <div class="card fade-in-up">
            <div class="card-header">
                <h3 class="text-base font-semibold text-ink-900">Hasil Terbaru</h3>
                <a href="{{ route('hasil.index') }}" class="btn-secondary text-xs py-1.5 px-3">Detail</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse($hasilTerbaru as $h)
                    <li class="px-6 py-3 flex items-center justify-between hover:bg-slate-50/60 transition">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-ink-900 truncate">{{ optional($h->siswa)->nama_siswa ?? '-' }}</div>
                            <div class="text-xs text-ink-500 truncate">{{ optional($h->quiz)->name ?? '-' }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-base font-bold bg-gradient-to-r from-brand-600 to-accent-600 bg-clip-text text-transparent">
                                {{ $h->score !== null ? number_format($h->score, 1) : '-' }}
                            </div>
                            <div class="text-[10px] text-ink-500">{{ $h->updated_at?->diffForHumans() }}</div>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-ink-500 text-sm">
                        <div class="text-3xl mb-2">🎯</div>
                        Belum ada hasil ujian
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
