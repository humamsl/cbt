@extends('layouts.app')
@section('title', 'Riwayat Aktivitas Registrasi Ujian')
@section('breadcrumb', 'CBT / Registrasi Ujian / Riwayat Aktivitas')

@section('content')
<x-page-header title="Riwayat Aktivitas Registrasi Ujian"
    subtitle="Jejak audit: siapa membuat, mengubah, menghapus, atau menduplikat tes apa, kapan">
    <x-slot:action><a href="{{ route('tes.index') }}" class="btn-secondary">← Kembali</a></x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" class="input flex-1 min-w-[200px]" placeholder="Cari nama tes...">
    <select name="action" class="select w-auto">
        <option value="">Semua aksi</option>
        <option value="created" @selected(request('action')=='created')>Dibuat</option>
        <option value="updated" @selected(request('action')=='updated')>Diubah</option>
        <option value="deleted" @selected(request('action')=='deleted')>Dihapus</option>
        <option value="duplicated" @selected(request('action')=='duplicated')>Diduplikat</option>
    </select>
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<div class="card table-wrap">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-ink-500 uppercase border-b border-slate-100">
                <th class="p-3">Waktu</th>
                <th class="p-3">Aksi</th>
                <th class="p-3">Nama Tes</th>
                <th class="p-3">Pelaku</th>
                <th class="p-3">Detail</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                @php
                    $badge = match($log->action) {
                        'created' => 'badge-success', 'updated' => 'badge-info',
                        'deleted' => 'badge-danger', 'duplicated' => 'badge-warning',
                        default => 'badge-muted',
                    };
                    $label = match($log->action) {
                        'created' => 'Dibuat', 'updated' => 'Diubah',
                        'deleted' => 'Dihapus', 'duplicated' => 'Diduplikat',
                        default => $log->action,
                    };
                @endphp
                <tr class="border-b border-slate-50 align-top">
                    <td class="p-3 whitespace-nowrap text-ink-600">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td class="p-3"><span class="{{ $badge }}">{{ $label }}</span></td>
                    <td class="p-3 font-medium text-ink-900">
                        {{ $log->quiz_name }}
                        @if($log->quiz_id && $log->action !== 'deleted')
                            <a href="{{ route('tes.edit', $log->quiz_id) }}" class="text-xs text-brand-600 hover:underline block">Buka tes #{{ $log->quiz_id }}</a>
                        @endif
                    </td>
                    <td class="p-3">
                        {{ $log->actor_name }}
                        <span class="badge-muted text-[10px] ml-1">{{ ucfirst($log->actor_type) }}</span>
                    </td>
                    <td class="p-3 text-xs text-ink-500">
                        @if($log->action === 'updated' && $log->meta)
                            <ul class="space-y-0.5">
                                @foreach(($log->meta['after'] ?? []) as $field => $to)
                                    <li>
                                        <span class="font-mono">{{ $field }}</span>:
                                        <span class="line-through text-rose-500">{{ $log->meta['before'][$field] ?? '—' }}</span>
                                        → <span class="text-emerald-600">{{ $to }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif($log->action === 'duplicated' && $log->meta)
                            dari "{{ $log->meta['source_quiz_name'] ?? '-' }}" (#{{ $log->meta['source_quiz_id'] ?? '-' }})
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-6 text-center text-ink-500">Belum ada aktivitas tercatat.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
