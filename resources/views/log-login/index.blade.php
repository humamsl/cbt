@extends('layouts.app')
@section('title', 'Log Login')
@section('breadcrumb', 'Admin / Log Login')

@section('content')
<x-page-header title="Log Login Sistem" subtitle="Riwayat percobaan login user (berhasil & gagal)">
    <x-slot:action>
        <button onclick="location.reload()" class="btn-secondary"><x-icon name="clock" class="w-4 h-4"/> Refresh</button>
    </x-slot:action>
</x-page-header>

{{-- Stat ringkas --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <x-stat-card label="Total Login" :value="number_format($stat['total'])" icon="chart" tone="brand"/>
    <x-stat-card label="Sukses" :value="number_format($stat['sukses'])" icon="check" tone="emerald"/>
    <x-stat-card label="Gagal" :value="number_format($stat['gagal'])" icon="trash" tone="rose"/>
    <x-stat-card label="Hari Ini" :value="number_format($stat['hari_ini'])" icon="clock" tone="sky"/>
</div>

{{-- Filter --}}
<form class="card card-pad mb-4 grid md:grid-cols-6 gap-2">
    <input name="q" value="{{ request('q') }}" class="input md:col-span-2" placeholder="Cari username...">
    <select name="guard" class="select">
        <option value="">Semua role</option>
        <option value="admin" @selected(request('guard')==='admin')>Admin</option>
        <option value="guru"  @selected(request('guard')==='guru')>Guru</option>
        <option value="siswa" @selected(request('guard')==='siswa')>Siswa</option>
    </select>
    <select name="status" class="select">
        <option value="">Semua status</option>
        <option value="sukses" @selected(request('status')==='sukses')>Sukses</option>
        <option value="gagal"  @selected(request('status')==='gagal')>Gagal</option>
    </select>
    <select name="device" class="select">
        <option value="">Semua device</option>
        <option value="desktop" @selected(request('device')==='desktop')>Desktop</option>
        <option value="mobile"  @selected(request('device')==='mobile')>Mobile</option>
        <option value="tablet"  @selected(request('device')==='tablet')>Tablet</option>
    </select>
    <input type="date" name="tanggal" value="{{ request('tanggal') }}" class="input">
</form>

<div class="card overflow-x-auto">
    <table class="table-modern">
        <thead><tr>
            <th>Waktu</th>
            <th>Username</th>
            <th>Role</th>
            <th class="text-center">Status</th>
            <th class="text-center">Percobaan</th>
            <th class="text-center">Device</th>
            <th>Browser / OS</th>
            <th>IP</th>
            <th>User Agent</th>
        </tr></thead>
        <tbody>
        @forelse($items as $a)
            <tr>
                <td class="text-xs whitespace-nowrap">
                    <div class="font-semibold text-ink-900">{{ $a->created_at->format('d M Y') }}</div>
                    <div class="text-ink-500">{{ $a->created_at->format('H:i:s') }}</div>
                </td>
                <td class="font-mono text-xs">{{ $a->username }}</td>
                <td><span class="badge-muted">{{ ucfirst($a->guard) }}</span></td>
                <td class="text-center">
                    @if($a->success)
                        <span class="badge-success">✓ Sukses</span>
                    @else
                        <span class="badge-danger">✕ Gagal</span>
                    @endif
                </td>
                <td class="text-center font-semibold">#{{ $a->attempt_no ?? 1 }}</td>
                <td class="text-center"><span class="{{ $a->device_badge }}">{{ ucfirst($a->device_type ?? '—') }}</span></td>
                <td class="text-xs">
                    <div class="font-semibold text-ink-900">{{ $a->browser ?? '—' }}</div>
                    <div class="text-ink-500">{{ $a->os ?? '—' }}</div>
                </td>
                <td class="font-mono text-xs">{{ $a->ip_address ?? '—' }}</td>
                <td class="text-[10px] text-ink-500 max-w-[280px] truncate" title="{{ $a->user_agent }}">
                    {{ \Illuminate\Support\Str::limit($a->user_agent, 50) }}
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center py-10 text-ink-500">
                <div class="text-3xl mb-2">📭</div>
                Belum ada log login
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
