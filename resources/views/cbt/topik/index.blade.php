@extends('layouts.app')
@section('title', 'Topik')
@section('breadcrumb', 'CBT / Topik')

@section('content')
<x-page-header title="Topik / Sub Bab" subtitle="Pengelompokan soal di dalam mapel, rombel, & tingkat">
    <x-slot:action><a href="{{ route('topik.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah</a></x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 max-w-md flex gap-2">
    <input name="q" value="{{ request('q') }}" class="input" placeholder="Cari topik...">
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<div class="card">
    <table class="table-modern">
        <thead>
            <tr>
                <th>Topik</th>
                <th>Mapel</th>
                <th>Tingkat</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($items as $t)
            <tr>
                <td class="font-semibold text-ink-900">{{ $t->topic }}</td>
                <td>{{ optional($t->mapel)->nama_mapel ?? '—' }}</td>
                <td class="text-center">{{ $t->tingkat ?? '—' }}</td>
                <td>@if($t->is_active)<span class="badge-success">Aktif</span>@else<span class="badge-muted">Non-aktif</span>@endif</td>
                <td class="text-right">
                    <a href="{{ route('topik.edit', $t) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    <form method="POST" action="{{ route('topik.destroy', $t) }}" class="inline" onsubmit="return confirm('Hapus?')">
                        @csrf @method('DELETE')<button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center py-8 text-ink-500">Belum ada topik.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
