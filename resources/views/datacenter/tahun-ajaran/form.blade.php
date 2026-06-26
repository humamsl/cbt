@extends('layouts.app')
@section('title', $item->exists ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran')
@section('breadcrumb', 'Data Center / Tahun Ajaran / Form')

@section('content')
<x-page-header :title="$item->exists ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran'"/>
<form method="POST" action="{{ $item->exists ? route('tahun-ajaran.update', $item) : route('tahun-ajaran.store') }}" class="card card-pad space-y-4 max-w-2xl">
    @csrf @if($item->exists) @method('PUT') @endif
    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="kode_tahun_ajaran" label="Kode" :value="$item->kode_tahun_ajaran" required placeholder="2526"/>
        <x-field name="nama_tahun_ajaran" label="Nama" :value="$item->nama_tahun_ajaran" required placeholder="2025/2026"/>
        <x-field type="select" name="semester" label="Semester" :value="$item->semester"
                 :options="['Ganjil'=>'Ganjil','Genap'=>'Genap']" required/>
        <x-field name="tanggal_mulai" type="date" label="Tanggal Mulai" :value="optional($item->tanggal_mulai)->format('Y-m-d')"/>
        <x-field name="tanggal_selesai" type="date" label="Tanggal Selesai" :value="optional($item->tanggal_selesai)->format('Y-m-d')"/>
    </div>
    <x-field type="checkbox" name="is_aktif" :value="$item->is_aktif" help="Jadikan tahun ajaran aktif"/>
    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('tahun-ajaran.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
