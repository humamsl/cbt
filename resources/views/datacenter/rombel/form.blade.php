@extends('layouts.app')
@section('title', $item->exists ? 'Edit Rombel' : 'Tambah Rombel')

@section('content')
<x-page-header :title="$item->exists ? 'Edit Rombongan Belajar' : 'Tambah Rombongan Belajar'"/>
<form method="POST" action="{{ $item->exists ? route('rombel.update', $item) : route('rombel.store') }}" class="card card-pad space-y-4 max-w-2xl">
    @csrf @if($item->exists) @method('PUT') @endif
    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="nama_rombel" label="Nama Rombel" :value="$item->nama_rombel" required placeholder="X IPA 1"/>
        <x-field type="select" name="tingkat" label="Tingkat Kelas" :value="$item->tingkat" required
                 :options="$tingkatList->mapWithKeys(fn($t) => [$t->nomor => $t->nama])->toArray()"/>
        <x-field type="select" name="jurusan_id" label="Jurusan" :value="$item->jurusan_id"
                 :options="$jurusan->pluck('nama_jurusan', 'id')->toArray()"/>
        <x-field type="select" name="tahun_ajaran_id" label="Tahun Ajaran" :value="$item->tahun_ajaran_id" required
                 :options="$tahunAjaran->mapWithKeys(fn($t) => [$t->id => $t->nama_tahun_ajaran.' ('.$t->semester.')'])->toArray()"/>
        <x-field type="select" name="wali_kelas_id" label="Wali Kelas" :value="$item->wali_kelas_id"
                 :options="$guru->pluck('nama_ptk', 'id')->toArray()"/>
        <x-field name="kapasitas" type="number" label="Kapasitas" :value="$item->kapasitas ?? 36"/>
    </div>
    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('rombel.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
