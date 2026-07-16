@extends('layouts.app')
@section('title', 'Registrasi Ujian')
@section('breadcrumb', 'CBT / Registrasi Ujian')

@section('content')
<x-page-header title="Registrasi Ujian" subtitle="Konfigurasi paket ujian online">
    <x-slot:action><a href="{{ route('tes.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Registrasi Ujian Baru</a></x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 max-w-md flex gap-2">
    <input name="q" value="{{ request('q') }}" class="input" placeholder="Cari nama tes...">
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<div class="grid gap-3">
    @forelse($items as $t)
        <div class="card card-pad">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="{{ $t->status_badge }}">{{ ucfirst($t->status) }}</span>
                        <span class="badge-muted">{{ optional($t->mapel)->nama_mapel ?? '🌐 Ujian Umum' }}</span>
                        @if($t->target_mode === 'per_siswa')
                            <span class="badge-info">👤 {{ $t->siswa_targets_count ?? $t->siswaTargets()->count() }} siswa terpilih</span>
                        @elseif($t->target_mode === 'per_tingkat')
                            {{-- Target per tingkat: tampilkan tingkatnya supaya admin bisa
                                 langsung memverifikasi ujian ini menyasar tingkat mana saja --}}
                            @forelse((array) $t->target_tingkat as $tk)
                                <span class="badge-info">Tingkat {{ $tk }}</span>
                            @empty
                                <span class="badge-warning">⚠ Tingkat belum diatur</span>
                            @endforelse
                        @else
                            @foreach($t->rombelTargets as $rb)
                                <span class="badge-info">{{ $rb->nama_rombel }}</span>
                            @endforeach
                            @if($t->rombelTargets->isEmpty() && $t->rombel)
                                <span class="badge-info">{{ $t->rombel->nama_rombel }}</span>
                            @endif
                        @endif
                    </div>
                    <div class="text-lg font-bold text-ink-900">{{ $t->name }}</div>
                    <div class="text-sm text-ink-500 mt-1">{{ Str::limit($t->description, 140) }}</div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <a href="{{ route('tes.questions', $t) }}" class="btn-secondary text-xs">
                        <x-icon name="document" class="w-4 h-4"/> Soal
                    </a>
                    <a href="{{ route('tes.edit', $t) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    <form method="POST" action="{{ route('tes.destroy', $t) }}" onsubmit="return confirm('Hapus tes?')">
                        @csrf @method('DELETE')<button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                    </form>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div><div class="text-xs text-ink-500">Durasi</div><div class="font-semibold">{{ $t->duration }} menit</div></div>
                <div><div class="text-xs text-ink-500">Soal</div><div class="font-semibold">{{ $t->questions_count }}</div></div>
                <div><div class="text-xs text-ink-500">Attempt</div><div class="font-semibold">{{ $t->attempts_count }}</div></div>
                <div><div class="text-xs text-ink-500">Periode</div>
                    <div class="text-xs">{{ optional($t->valid_from)->format('d/m H:i') }} — {{ optional($t->valid_upto)->format('d/m H:i') }}</div>
                </div>
            </div>
        </div>
    @empty
        <div class="card card-pad text-center text-ink-500">Belum ada tes.</div>
    @endforelse
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
