@extends('layouts.app')
@section('title', 'Hasil & Laporan')
@section('breadcrumb', 'CBT / Hasil')

@section('content')
<x-page-header title="Hasil & Laporan Ujian" subtitle="Rekap nilai siswa, statistik, dan analisis butir soal">
    <x-slot:action>
        <a href="{{ route('hasil.export.nilai', $filters) }}" class="btn-primary">
            <x-icon name="chart" class="w-4 h-4"/> Export Nilai (Excel)
        </a>
    </x-slot:action>
</x-page-header>

@include('cbt.hasil._nav', ['active' => 'index'])

<form class="card card-pad mb-4 grid md:grid-cols-5 gap-2">
    <input name="q" value="{{ request('q') }}" class="input md:col-span-2" placeholder="Cari NISN / nama siswa...">
    <select name="mapel" class="select">
        <option value="">Semua Mapel</option>
        @foreach($mapelList as $m)
            <option value="{{ $m->id }}" @selected(request('mapel')==$m->id)>{{ $m->nama_mapel }}</option>
        @endforeach
    </select>
    <select name="rombel" class="select">
        <option value="">Semua Rombel</option>
        @foreach($rombelList as $r)
            <option value="{{ $r->id }}" @selected(request('rombel')==$r->id)>{{ $r->nama_rombel }} (T{{ $r->tingkat }})</option>
        @endforeach
    </select>
    <select name="tingkat" class="select">
        <option value="">Semua Tingkat</option>
        @foreach($tingkatList as $t)
            <option value="{{ $t->nomor }}" @selected(request('tingkat')==$t->nomor)>{{ $t->nama }}</option>
        @endforeach
    </select>
    <div class="md:col-span-5 flex gap-2">
        <select name="quiz" class="select flex-1">
            <option value="">Semua Ujian</option>
            @foreach($quizzes as $q)
                <option value="{{ $q->id }}" @selected(request('quiz')==$q->id)>
                    {{ $q->name }} — {{ optional($q->mapel)->nama_mapel }}
                </option>
            @endforeach
        </select>
        <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/> Filter</button>
        @if(collect($filters)->filter()->isNotEmpty())
            <a href="{{ route('hasil.index') }}" class="btn-ghost">Reset</a>
        @endif
    </div>
</form>

<div class="card overflow-x-auto">
    <table class="table-modern">
        <thead><tr>
            <th>Siswa</th><th>Rombel</th><th>Ujian</th><th>Mapel</th>
            <th>Selesai</th><th class="text-right">Nilai</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($items as $h)
            @php $rombel = optional(optional($h->siswa)->rombelSekarang)->rombel; @endphp
            <tr>
                <td>
                    <div class="font-semibold text-ink-900">{{ optional($h->siswa)->nama_siswa ?? '-' }}</div>
                    <div class="text-xs text-ink-500 font-mono">{{ optional($h->siswa)->nisn }}</div>
                </td>
                <td>
                    <span class="badge-info">{{ optional($rombel)->nama_rombel ?? '-' }}</span>
                    <div class="text-[10px] text-ink-500">Tingkat {{ optional($rombel)->tingkat ?? '-' }}</div>
                </td>
                <td class="font-semibold">{{ optional($h->quiz)->name }}</td>
                <td>{{ optional($h->quiz->mapel ?? null)->nama_mapel ?? '-' }}</td>
                <td class="text-xs">{{ optional($h->time_end)->format('d M H:i') }}</td>
                <td class="text-right">
                    <div class="text-lg font-bold {{ $h->score >= 70 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $h->score !== null ? number_format($h->score, 1) : '-' }}
                    </div>
                    <div class="text-[10px] text-ink-500">B:{{ $h->correct_count }} S:{{ $h->wrong_count }} K:{{ $h->empty_count }}</div>
                </td>
                <td><a href="{{ route('hasil.detail', $h) }}" class="btn-ghost p-2"><x-icon name="arrow-right"/></a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center py-8 text-ink-500">Belum ada hasil ujian sesuai filter.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
