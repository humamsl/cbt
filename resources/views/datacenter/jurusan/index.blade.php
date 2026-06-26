@extends('layouts.app')
@section('title', 'Jurusan')
@section('breadcrumb', 'Data Center / Jurusan')

@section('content')
<x-page-header title="Jurusan" subtitle="Program keahlian / peminatan">
    <x-slot:action>
        <a href="{{ route('jurusan.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 max-w-md flex gap-2">
    <input name="q" value="{{ $q }}" class="input" placeholder="Cari jurusan...">
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<div class="card">
    <table class="table-modern">
        <thead><tr><th>Kode</th><th>Nama Jurusan</th><th>Singkatan</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($items as $it)
            <tr>
                <td class="font-mono text-xs">{{ $it->kode_jurusan }}</td>
                <td class="font-semibold text-ink-900">{{ $it->nama_jurusan }}</td>
                <td>{{ $it->singkatan }}</td>
                <td>@if($it->is_aktif)<span class="badge-success">Aktif</span>@else<span class="badge-muted">Non-aktif</span>@endif</td>
                <td class="text-right">
                    <a href="{{ route('jurusan.edit', $it) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    <form method="POST" action="{{ route('jurusan.destroy', $it) }}" class="inline" onsubmit="return confirm('Hapus jurusan ini?')">
                        @csrf @method('DELETE')
                        <button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center py-8 text-ink-500">Belum ada data.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
