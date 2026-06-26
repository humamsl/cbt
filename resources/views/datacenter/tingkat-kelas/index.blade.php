@extends('layouts.app')
@section('title', 'Tingkat Kelas')
@section('breadcrumb', 'Data Center / Tingkat Kelas')

@section('content')
<x-page-header title="Tingkat Kelas" subtitle="Master jenjang tingkat (7-12, dst)">
    <x-slot:action>
        <a href="{{ route('tingkat-kelas.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah</a>
    </x-slot:action>
</x-page-header>

<div class="card overflow-x-auto">
    <table class="table-modern">
        <thead><tr>
            <th>Kode</th><th>Nama</th><th>Nomor</th><th>Jenjang</th><th>Urutan</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($items as $it)
            <tr>
                <td class="font-mono text-xs">{{ $it->kode }}</td>
                <td class="font-semibold text-ink-900">{{ $it->nama }}</td>
                <td class="text-center">{{ $it->nomor }}</td>
                <td>{{ $it->jenjang ?? '—' }}</td>
                <td>{{ $it->urutan }}</td>
                <td>@if($it->is_aktif)<span class="badge-success">Aktif</span>@else<span class="badge-muted">Non-aktif</span>@endif</td>
                <td class="text-right">
                    <a href="{{ route('tingkat-kelas.edit', $it) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    <form method="POST" action="{{ route('tingkat-kelas.destroy', $it) }}" class="inline" onsubmit="return confirm('Hapus?')">
                        @csrf @method('DELETE')<button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center py-8 text-ink-500">Belum ada tingkat kelas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
