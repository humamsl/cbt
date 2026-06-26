@extends('layouts.app')
@section('title', 'Profil Sekolah')
@section('breadcrumb', 'Data Center / Profil Sekolah')

@section('content')
<x-page-header title="Profil Sekolah" subtitle="Identitas lembaga dan kontak"/>
<form method="POST" action="{{ route('sekolah.update') }}" class="card card-pad space-y-5">
    @csrf @method('PUT')
    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="npsn" label="NPSN" :value="$sekolah->npsn" required/>
        <x-field name="nama_sekolah" label="Nama Sekolah" :value="$sekolah->nama_sekolah" required/>
        <x-field type="select" name="jenjang" label="Jenjang" :value="$sekolah->jenjang"
                 :options="['SD'=>'SD','SMP'=>'SMP','SMA'=>'SMA','SMK'=>'SMK','MA'=>'MA']" required/>
        <x-field name="telepon" label="Telepon" :value="$sekolah->telepon"/>
        <x-field name="email" type="email" label="Email" :value="$sekolah->email"/>
        <x-field name="website" label="Website" :value="$sekolah->website"/>
        <x-field name="kepala_sekolah" label="Kepala Sekolah" :value="$sekolah->kepala_sekolah"/>
        <x-field name="nip_kepala_sekolah" label="NIP Kepala Sekolah" :value="$sekolah->nip_kepala_sekolah"/>
    </div>
    <x-field name="alamat" label="Alamat" :value="$sekolah->alamat"/>
    <div class="grid md:grid-cols-4 gap-4">
        <x-field name="kelurahan" label="Kelurahan" :value="$sekolah->kelurahan"/>
        <x-field name="kecamatan" label="Kecamatan" :value="$sekolah->kecamatan"/>
        <x-field name="kabupaten" label="Kabupaten" :value="$sekolah->kabupaten"/>
        <x-field name="provinsi" label="Provinsi" :value="$sekolah->provinsi"/>
    </div>
    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
        <button class="btn-primary"><x-icon name="check" class="w-4 h-4"/> Simpan</button>
    </div>
</form>
@endsection
