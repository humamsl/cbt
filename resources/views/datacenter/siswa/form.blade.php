@extends('layouts.app')
@section('title', $item->exists ? 'Edit Siswa' : 'Tambah Siswa')

@section('content')
<x-page-header :title="$item->exists ? 'Edit Data Siswa' : 'Tambah Data Siswa'"/>
<form method="POST" action="{{ $item->exists ? route('siswa.update', $item) : route('siswa.store') }}" class="card card-pad space-y-4 max-w-4xl">
    @csrf @if($item->exists) @method('PUT') @endif

    <div class="grid md:grid-cols-3 gap-4">
        <x-field name="nisn" label="NISN" :value="$item->nisn" required/>
        <x-field name="nis" label="NIS" :value="$item->nis"/>
        <x-field type="select" name="jenis_kelamin" label="Jenis Kelamin" :value="$item->jenis_kelamin"
                 :options="['L'=>'Laki-laki','P'=>'Perempuan']"/>
    </div>
    <x-field name="nama_siswa" label="Nama Lengkap" :value="$item->nama_siswa" required/>
    <div class="grid md:grid-cols-3 gap-4">
        <x-field name="tempat_lahir" label="Tempat Lahir" :value="$item->tempat_lahir"/>
        <x-field name="tanggal_lahir" type="date" label="Tanggal Lahir" :value="optional($item->tanggal_lahir)->format('Y-m-d')"/>
        <x-field name="agama" label="Agama" :value="$item->agama"/>
    </div>
    <x-field type="textarea" name="alamat" label="Alamat" :value="$item->alamat"/>
    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="nomor_hp" label="No HP" :value="$item->nomor_hp"/>
        <x-field name="email" type="email" label="Email" :value="$item->email"/>
        <x-field name="nama_ayah" label="Nama Ayah" :value="$item->nama_ayah"/>
        <x-field name="nama_ibu" label="Nama Ibu" :value="$item->nama_ibu"/>
        <x-field name="nomor_hp_ortu" label="No HP Orang Tua" :value="$item->nomor_hp_ortu"/>
        <x-field type="select" name="rombongan_belajar_id" label="Rombongan Belajar"
                 :value="optional($item->rombelSekarang)->rombongan_belajar_id"
                 :options="$rombel->mapWithKeys(fn($r) => [$r->id => $r->nama_rombel.' — '.optional($r->tahunAjaran)->nama_tahun_ajaran])->toArray()"/>
        <x-field name="password" type="password" label="Password" :help="$item->exists ? 'Kosongkan jika tidak diubah' : 'Default sama dengan NISN'"/>
    </div>
    <x-field type="checkbox" name="is_aktif" :value="$item->is_aktif ?? true"/>

    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('siswa.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
