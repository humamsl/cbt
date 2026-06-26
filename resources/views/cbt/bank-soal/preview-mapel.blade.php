@extends('layouts.app')
@section('title', 'Preview Soal per Mapel')
@section('breadcrumb', 'CBT / Bank Soal / Preview Mapel')

@section('content')
<x-page-header title="Preview Soal per Mata Pelajaran" subtitle="Pratinjau seluruh soal sesuai mapel, jenis, dan topik">
    <x-slot:action>
        <a href="{{ route('bank-soal.index') }}" class="btn-secondary">Kembali</a>
        @if($mapel && $items->isNotEmpty())
            <button type="button" onclick="window.print()" class="btn-secondary">🖨️ Cetak</button>
        @endif
    </x-slot:action>
</x-page-header>

{{-- FILTER --}}
<form class="card card-pad mb-4 grid md:grid-cols-4 gap-2 print:hidden">
    <select name="mapel" class="select md:col-span-2" required onchange="this.form.submit()">
        <option value="">-- Pilih Mapel --</option>
        @foreach($mapelList as $m)
            <option value="{{ $m->id }}" @selected(request('mapel')==$m->id)>
                {{ $m->kode_mapel }} — {{ $m->nama_mapel }}
            </option>
        @endforeach
    </select>
    <select name="jenis" class="select">
        <option value="">Semua jenis</option>
        @foreach($types as $t)
            <option value="{{ $t->slug }}" @selected(request('jenis')==$t->slug)>{{ $t->question_type }}</option>
        @endforeach
    </select>
    <select name="topik" class="select">
        <option value="">Semua topik</option>
        @foreach($topiks as $tp)
            <option value="{{ $tp->id }}" @selected(request('topik')==$tp->id)>{{ $tp->topic }}</option>
        @endforeach
    </select>
    <div class="md:col-span-4 flex gap-2">
        <button class="btn-primary"><x-icon name="search" class="w-4 h-4"/> Tampilkan</button>
        @if(request('mapel'))
            <a href="{{ route('bank-soal.preview.mapel') }}" class="btn-ghost">Reset</a>
        @endif
    </div>
</form>

@if(! $mapel)
    <div class="card card-pad text-center text-ink-500">
        Pilih mata pelajaran untuk pratinjau semua soal.
    </div>
@elseif($items->isEmpty())
    <div class="card card-pad text-center text-ink-500">
        Belum ada soal pada mapel <strong>{{ $mapel->nama_mapel }}</strong> sesuai filter.
    </div>
@else
    {{-- HEADER PRINT --}}
    <div class="card card-pad mb-4 flex flex-wrap gap-4 items-center justify-between">
        <div>
            <div class="text-xs text-ink-500 uppercase tracking-wide">Mata Pelajaran</div>
            <div class="text-xl font-bold text-ink-900">{{ $mapel->kode_mapel }} — {{ $mapel->nama_mapel }}</div>
            <div class="text-xs text-ink-600 mt-0.5">
                @if(request('jenis'))Jenis: <strong>{{ optional($types->firstWhere('slug', request('jenis')))->question_type }}</strong> · @endif
                @if(request('topik'))Topik: <strong>{{ optional($topiks->firstWhere('id', request('topik')))->topic }}</strong> · @endif
                Total <strong>{{ $items->count() }}</strong> soal
            </div>
        </div>
        <div class="text-right text-xs text-ink-500">
            Dicetak: {{ now()->translatedFormat('d F Y H:i') }}
        </div>
    </div>

    {{-- DAFTAR SOAL --}}
    <div class="space-y-4">
        @foreach($items as $i => $q)
            @php $typeSlug = strtolower((string) optional($q->type)->slug); @endphp
            <div class="card card-pad break-inside-avoid">
                <div class="flex items-start justify-between gap-2 mb-2 flex-wrap">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="w-7 h-7 rounded-full bg-brand-100 text-brand-700 grid place-items-center text-sm font-bold">
                            {{ $i + 1 }}
                        </span>
                        <span class="font-bold text-ink-900">{{ $q->title }}</span>
                        <span class="badge-info text-[10px]">{{ optional($q->type)->question_type }}</span>
                        @if($q->topic)<span class="badge-muted text-[10px]">{{ $q->topic->topic }}</span>@endif
                        <span class="badge-{{ ['mudah'=>'success','sedang'=>'warning','sulit'=>'danger'][$q->tingkat_kesulitan] ?? 'muted' }} text-[10px]">
                            {{ ucfirst($q->tingkat_kesulitan ?? '-') }}
                        </span>
                    </div>
                    <a href="{{ route('bank-soal.edit', $q) }}" class="btn-ghost p-1.5 print:hidden">
                        <x-icon name="edit"/>
                    </a>
                </div>

                <div class="prose prose-sm max-w-none ml-9 mb-3">
                    {!! $q->question !!}
                </div>

                {{-- OPSI --}}
                <div class="ml-9">
                    @if(in_array($typeSlug, ['pg', 'pgk', 'benar-salah']))
                        <div class="space-y-1">
                            @foreach($q->options as $j => $opt)
                                @php $isCorrect = (bool) $opt->is_correct; @endphp
                                <div class="flex items-start gap-2 text-sm {{ $isCorrect ? 'font-semibold text-emerald-700' : 'text-ink-800' }}">
                                    <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-bold shrink-0 {{ $isCorrect ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-700' }}">
                                        {{ $opt->label ?: chr(65 + $j) }}
                                    </span>
                                    <span class="flex-1">{!! $opt->option_text !!}</span>
                                    @if($isCorrect)<span class="text-emerald-600">✓</span>@endif
                                </div>
                            @endforeach
                        </div>
                    @elseif($typeSlug === 'fill-blank')
                        @php $jawabans = array_filter(array_map('trim', explode('|', (string) $q->correct_answer_text))); @endphp
                        <div class="text-sm">
                            <strong class="text-emerald-700">Kunci:</strong>
                            @if(empty($jawabans))
                                <em class="text-ink-500">— belum diisi —</em>
                            @else
                                @foreach($jawabans as $j)
                                    <span class="inline-block px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-mono text-xs mx-0.5">{{ $j }}</span>
                                @endforeach
                            @endif
                        </div>
                    @elseif($typeSlug === 'penjodohan')
                        @php $pairs = $q->options->groupBy('pair_group'); @endphp
                        <div class="space-y-1.5 text-sm">
                            @foreach($pairs as $group => $opts)
                                @php
                                    $left  = $opts->firstWhere('is_left_side', true);
                                    $right = $opts->firstWhere('is_left_side', false);
                                @endphp
                                <div class="flex items-center gap-2">
                                    <span class="flex-1">{!! optional($left)->option_text ?? '-' !!}</span>
                                    <span class="text-emerald-500">↔</span>
                                    <span class="flex-1 font-semibold text-emerald-700">{!! optional($right)->option_text ?? '-' !!}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($q->pembahasan)
                        <div class="mt-3 p-2.5 rounded border border-amber-200 bg-amber-50 text-xs">
                            <strong class="text-amber-700">💡 Pembahasan:</strong>
                            <div class="prose prose-xs max-w-none mt-1">{!! $q->pembahasan !!}</div>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <style>
        @media print {
            .sidebar, header, .btn-primary, .btn-secondary, .btn-ghost { display: none !important; }
            .card { border: 1px solid #ddd !important; box-shadow: none !important; }
            body { background: white !important; }
        }
    </style>
@endif
@endsection
