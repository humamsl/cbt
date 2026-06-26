@extends('layouts.app')
@section('title', $item->exists ? 'Edit Tingkat Kelas' : 'Tambah Tingkat Kelas')

@section('content')
<x-page-header :title="$item->exists ? 'Edit Tingkat Kelas' : 'Tambah Tingkat Kelas'"/>
<form method="POST" action="{{ $item->exists ? route('tingkat-kelas.update', $item) : route('tingkat-kelas.store') }}"
      class="card card-pad space-y-4 max-w-2xl">
    @csrf @if($item->exists) @method('PUT') @endif

    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="kode" label="Kode" :value="$item->kode" required placeholder="X, 10, 7, dst"/>
        <x-field name="nama" label="Nama Tampilan" :value="$item->nama" required placeholder="Kelas X / 10"/>
        <x-field name="nomor" type="number" label="Nomor (1-12)" :value="$item->nomor" required help="Untuk pengurutan & filter"/>
        <x-field type="select" name="jenjang" label="Jenjang" :value="$item->jenjang"
                 :options="['SD'=>'SD','SMP'=>'SMP','SMA'=>'SMA','SMK'=>'SMK','MA'=>'MA','MTs'=>'MTs']"/>
        <x-field name="urutan" type="number" label="Urutan Tampil" :value="$item->urutan ?? 0"/>
    </div>
    <x-field type="checkbox" name="is_aktif" :value="$item->is_aktif ?? true"/>

    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('tingkat-kelas.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
