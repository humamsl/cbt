@extends('layouts.app')
@section('title', 'Daftar Ujian')

@section('content')
<x-page-header title="Daftar Ujian Tersedia" subtitle="Pilih ujian untuk dikerjakan"/>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($quizzes as $q)
        <div class="card card-pad flex flex-col">
            <div class="flex items-center gap-2 mb-2 flex-wrap">
                <span class="badge-info">{{ optional($q->mapel)->nama_mapel ?? 'Umum' }}</span>
                <span class="{{ $q->status_badge }}">{{ ucfirst($q->status) }}</span>
            </div>
            <div class="text-lg font-bold text-ink-900">{{ $q->name }}</div>
            <div class="text-sm text-ink-500 mt-1 flex-1">{{ Str::limit($q->description, 100) }}</div>
            <div class="mt-3 flex items-center justify-between text-xs text-ink-500 border-t border-slate-100 pt-3">
                <span><x-icon name="clock" class="inline w-3.5 h-3.5"/> {{ $q->duration }} menit</span>
                <span>{{ $q->questions_count }} soal</span>
            </div>
            <form method="POST" action="{{ route('siswa.ujian.start', $q) }}" class="mt-4">
                @csrf
                <button class="btn-primary w-full">Mulai Ujian <x-icon name="arrow-right" class="w-4 h-4"/></button>
            </form>
        </div>
    @empty
        <div class="col-span-full card card-pad text-center text-ink-500">Belum ada ujian yang dapat dikerjakan.</div>
    @endforelse
</div>
<div class="mt-4">{{ $quizzes->links() }}</div>
@endsection
