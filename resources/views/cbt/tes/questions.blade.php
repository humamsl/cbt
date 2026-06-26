@extends('layouts.app')
@section('title', 'Kelola Soal: '.$tes->name)

@section('content')
<x-page-header :title="$tes->name" :subtitle="'Kelola soal dalam tes ini'">
    <x-slot:action>
        <a href="{{ route('tes.edit', $tes) }}" class="btn-secondary"><x-icon name="edit" class="w-4 h-4"/> Edit Registrasi</a>

        {{-- Dropdown export — soal yang sudah di-attach ke ujian ini saja --}}
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" class="btn-secondary" type="button"
                    @if($tes->questions->isEmpty()) disabled title="Tambahkan minimal 1 soal dulu" @endif>
                <x-icon name="chart" class="w-4 h-4"/> Export Soal
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak x-transition
                 class="absolute right-0 mt-2 w-64 card overflow-hidden z-30">
                <div class="px-4 py-2 text-[10px] text-ink-500 bg-slate-50 border-b border-slate-100 uppercase tracking-wide">
                    {{ $tes->questions->count() }} soal di tes ini
                </div>
                <a href="{{ route('tes.export.word', $tes) }}" class="block px-4 py-2.5 text-sm hover:bg-slate-50">
                    📝 Export ke Word (.docx)
                </a>
                <a href="{{ route('tes.export.pdf', $tes) }}" class="block px-4 py-2.5 text-sm hover:bg-slate-50">
                    📄 Export ke PDF (soal saja)
                </a>
                <a href="{{ route('tes.export.pdf', [$tes, 'with_answer' => 1]) }}" class="block px-4 py-2.5 text-sm hover:bg-slate-50 border-t border-slate-100">
                    📄 Export PDF + Kunci Jawaban
                </a>
            </div>
        </div>
    </x-slot:action>
</x-page-header>

<div class="grid lg:grid-cols-2 gap-6">
    {{-- Soal terpasang --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-base font-semibold">Soal Dalam Ujian ({{ $tes->questions->count() }})</h3>
                <p class="text-xs text-ink-500">Total Nilai: {{ number_format($tes->total_marks, 2) }}</p>
            </div>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($tes->questions as $idx => $qq)
                <li class="px-6 py-3 flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs text-ink-500">#{{ $idx + 1 }} &middot; {{ $qq->marks }} poin</div>
                        <div class="font-semibold text-ink-900">{{ $qq->question->title }}</div>
                    </div>
                    <form method="POST" action="{{ route('tes.detach-question', [$tes, $qq]) }}" onsubmit="return confirm('Hapus dari tes?')">
                        @csrf @method('DELETE')
                        <button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                    </form>
                </li>
            @empty
                <li class="px-6 py-6 text-center text-ink-500">Belum ada soal. Tambahkan dari panel kanan.</li>
            @endforelse
        </ul>
    </div>

    {{-- Bank soal tersedia --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-base font-semibold">Bank Soal Tersedia</h3>
        </div>
        <form method="GET" class="px-6 pt-4 flex gap-2">
            <input name="q" value="{{ request('q') }}" class="input" placeholder="Cari soal di bank...">
            <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
        </form>
        <ul class="divide-y divide-slate-100 mt-2">
            @forelse($available as $q)
                <li class="px-6 py-3 flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs text-ink-500">{{ optional($q->mapel)->nama_mapel ?? 'Tanpa mapel' }}</div>
                        <div class="font-semibold text-ink-900">{{ $q->title }}</div>
                    </div>
                    <form method="POST" action="{{ route('tes.attach-question', $tes) }}" class="flex gap-1 items-center">
                        @csrf
                        <input type="hidden" name="question_id" value="{{ $q->id }}">
                        <input type="number" step="0.1" name="marks" value="1" class="input w-20 text-center" title="Nilai">
                        <button class="btn-primary text-xs px-3 py-1.5"><x-icon name="plus" class="w-4 h-4"/></button>
                    </form>
                </li>
            @empty
                <li class="px-6 py-6 text-center text-ink-500">Tidak ada soal tersedia.</li>
            @endforelse
        </ul>
        <div class="px-6 pb-4">{{ $available->links() }}</div>
    </div>
</div>
@endsection
