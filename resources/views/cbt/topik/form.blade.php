@extends('layouts.app')
@section('title', $item->exists ? 'Edit Topik' : 'Tambah Topik')

@section('content')
@php $isGuru = (auth()->user()?->user_type ?? null) === 'guru'; @endphp

<x-page-header :title="$item->exists ? 'Edit Topik' : 'Tambah Topik'"
               :subtitle="$isGuru ? 'Pilihan terbatas pada mapel & tingkat yang Anda ajar' : 'Topik dikelompokkan per mapel & tingkat'"/>

<form method="POST" action="{{ $item->exists ? route('topik.update', $item) : route('topik.store') }}"
      class="card card-pad space-y-4 max-w-2xl">
    @csrf @if($item->exists) @method('PUT') @endif

    <x-field name="topic" label="Nama Topik" :value="$item->topic" required/>

    <div class="grid md:grid-cols-2 gap-4">
        <x-field type="select" name="mata_pelajaran_id" label="Mapel" :value="$item->mata_pelajaran_id" required
                 :options="$mapel->mapWithKeys(fn($m) => [$m->id => $m->kode_mapel.' — '.$m->nama_mapel])->toArray()"/>

        <div>
            <x-field type="select" name="tingkat"
                     :label="$isGuru ? 'Tingkat Kelas (wajib)' : 'Tingkat Kelas (opsional)'"
                     :value="$item->tingkat"
                     :required="$isGuru"
                     :options="$tingkat->mapWithKeys(fn($t) => [$t->nomor => $t->nama])->toArray()"/>
            @if($isGuru && $tingkatKosong)
                <p class="mt-1 text-xs text-rose-600">
                    Anda belum ditugaskan ke rombel manapun pada tahun ajaran ini, sehingga tidak ada
                    pilihan tingkat kelas. Hubungi admin untuk mengatur penugasan mapel &amp; rombel Anda.
                </p>
            @elseif($isGuru && !empty($tingkatBelumAktif))
                <p class="mt-1 text-xs text-rose-600">
                    Anda mengajar di rombel tingkat {{ implode(', ', $tingkatBelumAktif) }}, tapi tingkat
                    tersebut belum ada / belum diaktifkan di menu Master &raquo; Tingkat Kelas (Data Center).
                    Hubungi admin untuk menambahkan atau mengaktifkannya.
                </p>
            @endif
        </div>
    </div>

    <x-field type="checkbox" name="is_active" :value="$item->is_active ?? true"/>

    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('topik.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
