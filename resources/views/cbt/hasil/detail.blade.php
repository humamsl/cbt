@extends('layouts.app')
@section('title', 'Detail Hasil')

@section('content')
<x-page-header :title="'Detail Hasil: '.($attempt->siswa->nama_siswa ?? '-')"
               :subtitle="$attempt->quiz->name"/>

<div class="grid md:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Nilai" :value="number_format($attempt->score ?? 0, 1)" icon="chart" tone="brand"/>
    <x-stat-card label="Benar" :value="$attempt->correct_count" icon="check" tone="emerald"/>
    <x-stat-card label="Salah" :value="$attempt->wrong_count" icon="trash" tone="rose"/>
    <x-stat-card label="Kosong" :value="$attempt->empty_count" icon="document" tone="amber"/>
</div>

<div class="card">
    <div class="card-header"><h3 class="text-base font-semibold">Detail Jawaban</h3></div>
    <ul class="divide-y divide-slate-100">
    @foreach($attempt->answers as $idx => $a)
        @php $q = $a->quizQuestion->question; $correct = $q->options->firstWhere('is_correct', true); @endphp
        <li class="px-6 py-4">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div class="font-semibold text-ink-900">{{ $idx + 1 }}. {{ $q->title }}</div>
                @if($a->is_correct)<span class="badge-success">Benar</span>
                @elseif($a->is_correct === false)<span class="badge-danger">Salah</span>
                @else<span class="badge-muted">-</span>@endif
            </div>
            <div class="text-sm text-ink-600 mb-2 prose prose-sm max-w-none">{!! $q->question !!}</div>
            <div class="grid sm:grid-cols-2 gap-2 text-sm">
                <div class="p-2 rounded-lg bg-slate-50 border border-slate-100">
                    <div class="text-xs text-ink-500">Jawaban siswa</div>
                    <div class="prose prose-sm max-w-none">
                        {!! optional($a->option)->option_text ?? ($a->answer_text ?? '— kosong —') !!}
                    </div>
                </div>
                <div class="p-2 rounded-lg bg-emerald-50 border border-emerald-100">
                    <div class="text-xs text-emerald-700">Kunci</div>
                    <div class="prose prose-sm max-w-none">{!! optional($correct)->option_text ?? '-' !!}</div>
                </div>
            </div>
        </li>
    @endforeach
    </ul>
</div>
@endsection
