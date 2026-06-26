@extends('layouts.app')
@section('title', $item->exists ? 'Edit Mapel' : 'Tambah Mapel')

@section('content')
<x-page-header :title="$item->exists ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran'"/>
<form method="POST" action="{{ $item->exists ? route('mapel.update', $item) : route('mapel.store') }}" class="card card-pad space-y-4 max-w-2xl">
    @csrf @if($item->exists) @method('PUT') @endif
    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="kode_mapel" label="Kode" :value="$item->kode_mapel" required/>
        <x-field name="nama_mapel" label="Nama Mapel" :value="$item->nama_mapel" required/>
        <x-field name="kelompok" label="Kelompok" :value="$item->kelompok" placeholder="Umum / Kejuruan / Muatan Lokal"/>
        <x-field name="tingkat" type="number" label="Tingkat" :value="$item->tingkat" placeholder="10/11/12"/>
        <x-field type="select" name="jurusan_id" label="Jurusan" :value="$item->jurusan_id"
                 :options="$jurusan->pluck('nama_jurusan', 'id')->toArray()"/>
    </div>
    <x-field type="textarea" name="deskripsi" label="Deskripsi" :value="$item->deskripsi"/>
    <x-field type="checkbox" name="is_aktif" :value="$item->is_aktif ?? true"/>
    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('mapel.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
