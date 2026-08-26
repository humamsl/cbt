@extends('layouts.app')
@section('title', 'Hasil Ujian')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="card overflow-hidden">
        <div class="p-6 sm:p-8 bg-gradient-to-br from-brand-600 to-brand-800 text-white text-center">
            <p class="text-brand-100 text-sm">Selesai mengerjakan</p>
            <h2 class="text-2xl font-bold mt-1">{{ $quiz->name }}</h2>
            @if($quiz->show_score)
                {{-- nilai = skala 0–100 otomatis, bukan poin mentah --}}
                <div class="mt-6 text-6xl font-bold">{{ number_format($attempt->nilai ?? 0, 1) }}</div>
                <p class="text-brand-100 text-sm mt-2">Nilai Anda</p>
            @else
                <div class="mt-6 text-5xl">🔒</div>
                <p class="text-brand-100 text-sm mt-2">Jawaban Anda sudah terkirim.<br>Nilai tidak ditampilkan untuk ujian ini.</p>
            @endif
        </div>
        @if($quiz->show_score)
        <div class="grid grid-cols-3 divide-x divide-slate-100">
            <div class="p-3 sm:p-5 text-center">
                <div class="text-2xl font-bold text-emerald-600">{{ $attempt->correct_count }}</div>
                <div class="text-xs text-ink-500 mt-1">Benar</div>
            </div>
            <div class="p-3 sm:p-5 text-center">
                <div class="text-2xl font-bold text-rose-600">{{ $attempt->wrong_count }}</div>
                <div class="text-xs text-ink-500 mt-1">Salah</div>
            </div>
            <div class="p-3 sm:p-5 text-center">
                <div class="text-2xl font-bold text-amber-600">{{ $attempt->empty_count }}</div>
                <div class="text-xs text-ink-500 mt-1">Kosong</div>
            </div>
        </div>
        @endif
        <div class="px-4 sm:px-6 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-sm">
            <div class="text-ink-500">Durasi: {{ $attempt->time_start?->diffInMinutes($attempt->time_end ?? now()) }} menit</div>
            <a href="{{ route('siswa.riwayat') }}" class="btn-primary">Lihat Riwayat</a>
        </div>
    </div>
</div>
@endsection
