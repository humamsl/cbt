@extends('layouts.app')
@section('title', 'Tahun Ajaran')
@section('breadcrumb', 'Data Center / Tahun Ajaran')

@section('content')
<x-page-header title="Tahun Ajaran" subtitle="Periode akademik aktif sekolah">
    <x-slot:action>
        <a href="{{ route('tahun-ajaran.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah</a>
    </x-slot:action>
</x-page-header>

<div class="card">
    <table class="table-modern">
        <thead><tr><th>Kode</th><th>Tahun Ajaran</th><th>Semester</th><th>Periode</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($items as $it)
            <tr>
                <td class="font-semibold">{{ $it->kode_tahun_ajaran }}</td>
                <td>{{ $it->nama_tahun_ajaran }}</td>
                <td>{{ $it->semester }}</td>
                <td class="text-xs text-ink-500">{{ optional($it->tanggal_mulai)->format('d M Y') }} — {{ optional($it->tanggal_selesai)->format('d M Y') }}</td>
                <td>@if($it->is_aktif)<span class="badge-success">Aktif</span>@else<span class="badge-muted">Non-aktif</span>@endif</td>
                <td class="text-right">
                    <a href="{{ route('tahun-ajaran.edit', $it) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    <form method="POST" action="{{ route('tahun-ajaran.destroy', $it) }}" class="inline" onsubmit="return confirm('Hapus tahun ajaran ini?')">
                        @csrf @method('DELETE')
                        <button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center py-8 text-ink-500">Belum ada data.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
