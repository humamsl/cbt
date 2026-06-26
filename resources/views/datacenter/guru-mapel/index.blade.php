@extends('layouts.app')
@section('title', 'Data Guru Mata Pelajaran')
@section('breadcrumb', 'Data Center / Guru Mapel')

@section('content')
<x-page-header title="Data Guru Mata Pelajaran" subtitle="Atur guru mengajar mapel & rombel mana saja">
    <x-slot:action>
        <a href="{{ route('guru-mapel.import.form') }}" class="btn-secondary">
            <x-icon name="document" class="w-4 h-4"/> Import
        </a>
        <a href="{{ route('guru-mapel.export.excel', request()->query()) }}" class="btn-secondary">
            <x-icon name="chart" class="w-4 h-4"/> Export Excel
        </a>
        <a href="{{ route('guru-mapel.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah Data</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 grid md:grid-cols-4 gap-2">
    <select name="guru" class="select"><option value="">Semua guru</option>
        @foreach($guruList as $g)<option value="{{ $g->id }}" @selected(request('guru')==$g->id)>{{ $g->nama_ptk }}</option>@endforeach
    </select>
    <select name="mapel" class="select"><option value="">Semua mapel</option>
        @foreach($mapelList as $m)<option value="{{ $m->id }}" @selected(request('mapel')==$m->id)>{{ $m->nama_mapel }}</option>@endforeach
    </select>
    <select name="ta" class="select"><option value="">Semua TA</option>
        @foreach($taList as $t)<option value="{{ $t->id }}" @selected(request('ta')==$t->id)>{{ $t->nama_tahun_ajaran }}</option>@endforeach
    </select>
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<div class="card overflow-x-auto">
    <table class="table-modern">
        <thead><tr>
            <th>Guru</th><th>Mata Pelajaran</th><th>Tingkat</th><th>Rombel</th><th>Tahun Ajaran</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($items as $gm)
            <tr>
                <td class="font-semibold text-ink-900">{{ optional($gm->guru)->nama_ptk ?? '-' }}
                    <div class="text-[10px] text-ink-500 font-mono">{{ optional($gm->guru)->nip }}</div>
                </td>
                <td>{{ optional($gm->mapel)->nama_mapel ?? '-' }}</td>
                <td class="text-center">{{ optional($gm->rombel)->tingkat ?? '-' }}</td>
                <td><span class="badge-info">{{ optional($gm->rombel)->nama_rombel ?? '-' }}</span></td>
                <td class="text-xs">{{ optional($gm->tahunAjaran)->nama_tahun_ajaran ?? '-' }}</td>
                <td class="text-right">
                    <a href="{{ route('guru-mapel.edit', $gm) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    <form method="POST" action="{{ route('guru-mapel.destroy', $gm) }}" class="inline" onsubmit="return confirm('Hapus Data?')">
                        @csrf @method('DELETE')<button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center py-8 text-ink-500">Belum ada Data.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
