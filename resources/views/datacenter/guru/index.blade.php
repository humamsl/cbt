@extends('layouts.app')
@section('title', 'Data Guru')
@section('breadcrumb', 'Data Center / Guru')

@section('content')
<x-page-header title="Data Guru" subtitle="Pendidik dan tenaga kependidikan">
    <x-slot:action>
        <a href="{{ route('guru.import.form') }}" class="btn-secondary">
            <x-icon name="document" class="w-4 h-4"/> Import
        </a>
        <a href="{{ route('guru.export.excel', request()->query()) }}" class="btn-secondary">
            <x-icon name="chart" class="w-4 h-4"/> Export Excel
        </a>
        <a href="{{ route('guru.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah Guru</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 max-w-md flex gap-2">
    <input name="q" value="{{ request('q') }}" class="input" placeholder="Cari nama atau NIP...">
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<div class="card">
    <table class="table-modern">
        <thead><tr><th>NIP</th><th>Nama</th><th>JK</th><th>Jabatan</th><th>Status</th><th>Aktif</th><th></th></tr></thead>
        <tbody>
        @forelse($items as $g)
            <tr>
                <td class="font-mono text-xs">{{ $g->nip }}</td>
                <td class="flex items-center gap-2 font-semibold text-ink-900">
                    <img src="{{ $g->profile_photo_url }}" class="w-8 h-8 rounded-full object-cover">
                    <div>
                        {{ $g->nama_ptk }}
                        <div class="text-xs text-ink-500 font-normal">{{ $g->email ?: '—' }}</div>
                    </div>
                </td>
                <td>{{ $g->jenis_kelamin }}</td>
                <td>{{ $g->jabatan ?: '—' }}</td>
                <td>{{ $g->status_kepegawaian ?: '—' }}</td>
                <td>@if($g->is_aktif)<span class="badge-success">Aktif</span>@else<span class="badge-muted">Non-aktif</span>@endif</td>
                <td class="text-right">
                    <a href="{{ route('guru.edit', $g) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    <form method="POST" action="{{ route('guru.destroy', $g) }}" class="inline" onsubmit="return confirm('Hapus guru ini?')">
                        @csrf @method('DELETE')<button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center py-8 text-ink-500">Belum ada data.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
