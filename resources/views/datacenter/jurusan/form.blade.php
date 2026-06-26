@extends('layouts.app')
@section('title', $item->exists ? 'Edit Jurusan' : 'Tambah Jurusan')

@section('content')
<x-page-header :title="$item->exists ? 'Edit Jurusan' : 'Tambah Jurusan'"/>
<form method="POST" action="{{ $item->exists ? route('jurusan.update', $item) : route('jurusan.store') }}" class="card card-pad space-y-4 max-w-2xl">
    @csrf @if($item->exists) @method('PUT') @endif
    <div class="grid md:grid-cols-3 gap-4">
        <x-field name="kode_jurusan" label="Kode" :value="$item->kode_jurusan" required/>
        <x-field name="nama_jurusan" label="Nama Jurusan" :value="$item->nama_jurusan" required class="md:col-span-2"/>
        <x-field name="singkatan" label="Singkatan" :value="$item->singkatan"/>
    </div>
    <x-field type="textarea" name="deskripsi" label="Deskripsi" :value="$item->deskripsi"/>
    <x-field type="checkbox" name="is_aktif" :value="$item->is_aktif ?? true"/>
    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('jurusan.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
