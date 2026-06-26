@extends('layouts.app')
@section('title', 'Token Sesi')
@section('breadcrumb', 'CBT / Token Sesi')

@section('content')
<x-page-header title="Token Sesi" subtitle="Token untuk membuka akses ujian">
    <x-slot:action><a href="{{ route('token-sesi.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Buat Token</a></x-slot:action>
</x-page-header>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($items as $t)
        <div class="card card-pad">
            <div class="flex items-center justify-between mb-2">
                <span class="badge-info">{{ optional($t->tahunAjaran)->nama_tahun_ajaran ?? 'Umum' }}</span>
                @if($t->is_active)<span class="badge-success">Aktif</span>@else<span class="badge-muted">Non-aktif</span>@endif
            </div>
            <div class="font-mono text-3xl font-bold tracking-wider text-brand-600">{{ $t->token }}</div>
            <div class="text-sm text-ink-700 mt-2">{{ $t->nama_sesi ?? 'Sesi tanpa nama' }}</div>
            <div class="text-xs text-ink-500 mt-1">
                {{ optional($t->valid_from)->format('d M H:i') }} — {{ optional($t->valid_upto)->format('d M H:i') }}
            </div>
            <div class="flex items-center gap-1 mt-3">
                <a href="{{ route('token-sesi.edit', $t) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                <form method="POST" action="{{ route('token-sesi.destroy', $t) }}" onsubmit="return confirm('Hapus token?')">
                    @csrf @method('DELETE')<button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                </form>
            </div>
        </div>
    @empty
        <div class="col-span-full card card-pad text-center text-ink-500">Belum ada token sesi.</div>
    @endforelse
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
