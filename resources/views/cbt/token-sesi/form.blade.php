@extends('layouts.app')
@section('title', $item->exists ? 'Edit Token' : 'Buat Token')

@section('content')
<x-page-header :title="$item->exists ? 'Edit Token Sesi' : 'Buat Token Sesi'"/>
<form method="POST" action="{{ $item->exists ? route('token-sesi.update', $item) : route('token-sesi.store') }}" class="card card-pad space-y-4 max-w-xl">
    @csrf @if($item->exists) @method('PUT') @endif
    <x-field name="token" label="Token" :value="$item->token" required class="font-mono uppercase tracking-wider"/>
    <x-field name="nama_sesi" label="Nama Sesi" :value="$item->nama_sesi" placeholder="Sesi Pagi, Gelombang 1, dll."/>
    <x-field type="select" name="tahun_ajaran_id" label="Tahun Ajaran" :value="$item->tahun_ajaran_id"
             :options="$tahunAjaran->pluck('nama_tahun_ajaran', 'id')->toArray()"/>
    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="valid_from" type="datetime-local" label="Berlaku Dari"
                 :value="$item->valid_from ? $item->valid_from->format('Y-m-d\\TH:i') : null"/>
        <x-field name="valid_upto" type="datetime-local" label="Berlaku Sampai"
                 :value="$item->valid_upto ? $item->valid_upto->format('Y-m-d\\TH:i') : null"/>
    </div>
    <x-field type="checkbox" name="is_active" :value="$item->is_active ?? true"/>
    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('token-sesi.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
