@extends('layouts.app')
@section('title', 'Rombongan Belajar')
@section('breadcrumb', 'Data Center / Rombel')

@section('content')
<x-page-header title="Rombongan Belajar" subtitle="Kelas / rombongan per tahun ajaran">
    <x-slot:action>
        <a href="{{ route('rombel.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" class="input flex-1 min-w-[200px]" placeholder="Cari rombel...">
    <select name="ta" class="select w-48">
        <option value="">Semua TA</option>
        @foreach($tahunAjaran as $ta)
            <option value="{{ $ta->id }}" @selected(request('ta')==$ta->id)>{{ $ta->nama_tahun_ajaran }} ({{ $ta->semester }})</option>
        @endforeach
    </select>
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<div class="card">
    <table class="table-modern">
        <thead><tr><th>Tingkat</th><th>Nama Rombel</th><th>Jurusan</th><th>TA</th><th>Wali Kelas</th><th>Kapasitas</th><th></th></tr></thead>
        <tbody>
        @forelse($items as $it)
            <tr>
                <td>{{ $it->tingkat }}</td>
                <td class="font-semibold text-ink-900">{{ $it->nama_rombel }}</td>
                <td>{{ optional($it->jurusan)->nama_jurusan ?? '—' }}</td>
                <td class="text-xs">{{ optional($it->tahunAjaran)->nama_tahun_ajaran }}</td>
                <td>{{ optional($it->waliKelas)->nama_ptk ?? '—' }}</td>
                <td>{{ $it->kapasitas }}</td>
                <td class="text-right">
                    <a href="{{ route('rombel.edit', $it) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    <form method="POST" action="{{ route('rombel.destroy', $it) }}" class="inline" onsubmit="return confirm('Hapus?')">
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
