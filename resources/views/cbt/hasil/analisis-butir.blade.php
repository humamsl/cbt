@extends('layouts.app')
@section('title', 'Analisis Butir Soal')
@section('breadcrumb', 'CBT / Hasil / Analisis Butir')

@section('content')
<x-page-header title="Analisis Butir Soal & Evaluasi" subtitle="Tingkat kesukaran (P), daya pembeda (D), dan rekomendasi tiap soal">
    @if($quiz && count($items))
        <x-slot:action>
            <a href="{{ route('hasil.analisis.export', ['quiz' => $quiz->id]) }}" class="btn-primary">
                <x-icon name="chart" class="w-4 h-4"/> Export Analisis (Excel)
            </a>
        </x-slot:action>
    @endif
</x-page-header>

@include('cbt.hasil._nav', ['active' => 'analisis'])

<form class="card card-pad mb-4 flex gap-2">
    <select name="quiz" class="select flex-1" required>
        <option value="">-- Pilih Ujian --</option>
        @foreach($quizzes as $q)
            <option value="{{ $q->id }}" @selected(request('quiz')==$q->id)>
                {{ $q->name }} — {{ optional($q->mapel)->nama_mapel }}
            </option>
        @endforeach
    </select>
    <button class="btn-primary">Analisis</button>
</form>

@if(! $quiz)
    <div class="card card-pad text-center text-ink-500">
        Pilih ujian untuk melihat analisis butir soal.
    </div>
@elseif(empty($items))
    <div class="card card-pad text-center text-ink-500">
        Belum ada attempt selesai pada ujian ini.
    </div>
@else
    {{-- LEGEND --}}
    <div class="card card-pad mb-4 text-xs space-y-2">
        <div class="font-semibold text-ink-900">Keterangan:</div>
        <div class="grid md:grid-cols-3 gap-2">
            <div>
                <span class="font-semibold">P (Tingkat Kesukaran)</span>:
                <span class="badge-success">&gt; 0.70 Mudah</span>
                <span class="badge-warning">0.30–0.70 Sedang</span>
                <span class="badge-danger">&lt; 0.30 Sukar</span>
            </div>
            <div>
                <span class="font-semibold">D (Daya Pembeda)</span>:
                <span class="badge-success">&gt; 0.30 Baik</span>
                <span class="badge-warning">0.20–0.30 Cukup</span>
                <span class="badge-danger">&lt; 0.20 Jelek</span>
            </div>
            <div>
                <span class="font-semibold">Rekomendasi</span>:
                <span class="badge-success">Diterima</span>
                <span class="badge-warning">Perlu Revisi</span>
                <span class="badge-danger">Dibuang/Ganti</span>
            </div>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>No</th><th>Judul Soal</th><th>Tipe</th>
                    <th class="text-center">% Benar</th>
                    <th class="text-center">P</th>
                    <th class="text-center">Kesukaran</th>
                    <th class="text-center">D</th>
                    <th class="text-center">Daya Pembeda</th>
                    <th class="text-center">Rekomendasi</th>
                </tr>
            </thead>
            <tbody>
            @foreach($items as $i => $it)
                @php
                    $pBadge = match($it['p_kategori']) {
                        'Mudah'  => 'badge-success',
                        'Sedang' => 'badge-warning',
                        default  => 'badge-danger',
                    };
                    $dBadge = match($it['d_kategori']) {
                        'Sangat Baik', 'Baik' => 'badge-success',
                        'Cukup'  => 'badge-warning',
                        default  => 'badge-danger',
                    };
                    $rBadge = match($it['rekomendasi']) {
                        'Diterima'        => 'badge-success',
                        'Perlu Revisi'    => 'badge-warning',
                        default           => 'badge-danger',
                    };
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="font-semibold text-ink-900">{{ $it['title'] }}</td>
                    <td class="text-xs">{{ $it['tipe'] }}</td>
                    <td class="text-center">{{ number_format($it['percent_correct'], 1) }}%</td>
                    <td class="text-center font-mono">{{ number_format($it['p'], 3) }}</td>
                    <td class="text-center"><span class="{{ $pBadge }}">{{ $it['p_kategori'] }}</span></td>
                    <td class="text-center font-mono">{{ number_format($it['d'], 3) }}</td>
                    <td class="text-center"><span class="{{ $dBadge }}">{{ $it['d_kategori'] }}</span></td>
                    <td class="text-center"><span class="{{ $rBadge }}">{{ $it['rekomendasi'] }}</span></td>
                </tr>
                @if(! empty($it['opsi']))
                    <tr class="bg-slate-50/50">
                        <td></td>
                        <td colspan="8" class="text-xs">
                            <div class="font-semibold text-ink-700 mb-1.5">Distribusi pemilih:</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($it['opsi'] as $o)
                                    <div class="px-2.5 py-1 rounded border text-[11px] {{ $o['is_correct'] ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-ink-600' }}">
                                        <strong>{{ $o['label'] }}</strong>
                                        @if($o['is_correct'])<span class="text-emerald-600">✓</span>@endif
                                        — {{ $o['count'] }} ({{ number_format($o['percent'], 1) }}%)
                                    </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endif
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
