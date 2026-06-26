@extends('layouts.app')
@section('title', 'Riwayat Ujian Saya')

@section('content')
<x-page-header title="Riwayat & Nilai" subtitle="Daftar ujian yang pernah Anda kerjakan"/>

<div class="card">
    <table class="table-modern">
        <thead><tr><th>Ujian</th><th>Mapel</th><th>Tanggal</th><th class="text-right">Nilai</th><th></th></tr></thead>
        <tbody>
        @forelse($items as $r)
            <tr>
                <td class="font-semibold text-ink-900">{{ optional($r->quiz)->name }}</td>
                <td>{{ optional($r->quiz->mapel ?? null)->nama_mapel ?? '-' }}</td>
                <td>{{ $r->created_at->format('d M Y H:i') }}</td>
                <td class="text-right font-bold text-brand-600">{{ $r->score !== null ? number_format($r->score, 1) : '-' }}</td>
                <td><span class="{{ $r->is_done ? 'badge-success' : 'badge-warning' }}">{{ $r->is_done ? 'Selesai' : 'Dikerjakan' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center py-8 text-ink-500">Belum ada riwayat.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
