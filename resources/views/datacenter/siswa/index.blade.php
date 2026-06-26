@extends('layouts.app')
@section('title', 'Data Siswa')
@section('breadcrumb', 'Data Center / Siswa')

@section('content')
<x-page-header title="Data Siswa" subtitle="Peserta didik aktif sekolah">
    <x-slot:action>
        <a href="{{ route('siswa.import.form') }}" class="btn-secondary">
            <x-icon name="document" class="w-4 h-4"/> Import
        </a>
        <a href="{{ route('siswa.export.excel', request()->query()) }}" class="btn-secondary">
            <x-icon name="chart" class="w-4 h-4"/> Export Excel
        </a>
        <a href="{{ route('siswa.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah Siswa</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 max-w-md flex gap-2">
    <input name="q" value="{{ request('q') }}" class="input" placeholder="Cari nama, NISN, atau NIS...">
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<div class="card">
    <table class="table-modern">
        <thead><tr><th>NISN</th><th>Nama Siswa</th><th>JK</th><th>Kelas Sekarang</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($items as $s)
            <tr>
                <td class="font-mono text-xs">{{ $s->nisn }}</td>
                <td class="flex items-center gap-2 font-semibold text-ink-900">
                    <img src="{{ $s->profile_photo_url }}" class="w-8 h-8 rounded-full object-cover">
                    <div>
                        {{ $s->nama_siswa }}
                        <div class="text-xs text-ink-500 font-normal">NIS: {{ $s->nis ?: '—' }}</div>
                    </div>
                </td>
                <td>{{ $s->jenis_kelamin }}</td>
                <td>{{ optional($s->rombelSekarang?->rombel ?? null)->nama_rombel ?? '—' }}</td>
                <td>@if($s->is_aktif)<span class="badge-success">Aktif</span>@else<span class="badge-muted">Non-aktif</span>@endif</td>
                <td class="text-right">
                    <a href="{{ route('siswa.edit', $s) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    <form method="POST" action="{{ route('siswa.destroy', $s) }}" class="inline" onsubmit="return confirm('Hapus siswa ini?')">
                        @csrf @method('DELETE')<button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center py-8 text-ink-500">Belum ada data siswa.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
