@extends('layouts.app')
@section('title', $item->exists ? 'Edit Data Guru' : 'Tambah Data Guru')

@section('content')
<x-page-header :title="$item->exists ? 'Edit Data Guru Mapel' : 'Tambah Data Guru Mapel'"
               :subtitle="'Pilih guru, mapel, tingkat, dan rombel — bisa pilih banyak rombel sekaligus'"/>

<form method="POST" action="{{ $item->exists ? route('guru-mapel.update', $item) : route('guru-mapel.store') }}"
      class="card card-pad space-y-4 max-w-3xl"
      x-data="{ tingkat: '{{ old('tingkat', optional($item->rombel)->tingkat ?? '') }}' }">
    @csrf @if($item->exists) @method('PUT') @endif

    <div class="grid md:grid-cols-2 gap-4">
        <x-field type="select" name="guru_id" label="Guru / PTK" :value="$item->guru_id" required
                 :options="$guruList->mapWithKeys(fn($g) => [$g->id => $g->nip.' — '.$g->nama_ptk])->toArray()"/>
        <x-field type="select" name="mata_pelajaran_id" label="Mata Pelajaran" :value="$item->mata_pelajaran_id" required
                 :options="$mapelList->mapWithKeys(fn($m) => [$m->id => $m->kode_mapel.' — '.$m->nama_mapel])->toArray()"/>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <x-field type="select" name="tahun_ajaran_id" label="Tahun Ajaran" :value="$item->tahun_ajaran_id" required
                 :options="$taList->mapWithKeys(fn($t) => [$t->id => $t->nama_tahun_ajaran.' ('.$t->semester.')'])->toArray()"/>

        <div>
            <label class="label">Tingkat Kelas <span class="text-xs text-ink-500">(filter rombel)</span></label>
            <select x-model="tingkat" class="select">
                <option value="">— Semua Tingkat —</option>
                @foreach($tingkatList as $tk)
                    <option value="{{ $tk->nomor }}">{{ $tk->nama }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- ROMBEL multi-select (filter berdasarkan tingkat) --}}
    @if($item->exists)
        {{-- mode edit: single select --}}
        <x-field type="select" name="rombongan_belajar_id" label="Rombel" :value="$item->rombongan_belajar_id" required
                 :options="$rombelList->mapWithKeys(fn($r) => [$r->id => $r->nama_rombel.' (TA '.optional($r->tahunAjaran)->nama_tahun_ajaran.')'])->toArray()"/>
    @else
        {{-- mode create: multi-select --}}
        <div>
            <label class="label">Rombel <span class="text-rose-500">*</span>
                <span class="text-xs text-ink-500 font-normal">(centang lebih dari satu untuk assign massal)</span>
            </label>
            <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-64 overflow-y-auto p-3 rounded-lg border border-slate-200 bg-slate-50">
                @foreach($rombelList as $r)
                    <label class="flex items-center gap-2 p-2 rounded hover:bg-white cursor-pointer text-sm"
                           x-show="!tingkat || tingkat == '{{ $r->tingkat }}'">
                        <input type="checkbox" name="rombongan_belajar_id[]" value="{{ $r->id }}"
                               class="rounded text-brand-600 focus:ring-brand-500">
                        <span>
                            <strong>{{ $r->nama_rombel }}</strong>
                            <span class="text-xs text-ink-500 block">Tingkat {{ $r->tingkat }} · TA {{ optional($r->tahunAjaran)->nama_tahun_ajaran }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('rombongan_belajar_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            @error('rombongan_belajar_id.*')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
    @endif

    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('guru-mapel.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
