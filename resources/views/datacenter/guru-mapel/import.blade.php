@extends('layouts.app')
@section('title', 'Import Guru Mapel')
@section('breadcrumb', 'Data Center / Guru Mapel / Import')

@section('content')
<x-page-header title="Import Data Guru Mapel" subtitle="Upload file Excel (.xlsx) untuk menambah assignment guru ↔ mapel ↔ rombel massal">
    <x-slot:action>
        <a href="{{ route('guru-mapel.index') }}" class="btn-secondary">Kembali</a>
    </x-slot:action>
</x-page-header>

<div class="grid lg:grid-cols-2 gap-6">
    <form method="POST" action="{{ route('guru-mapel.import.store') }}" enctype="multipart/form-data"
          class="card card-pad space-y-4">
        @csrf
        <h3 class="font-semibold text-ink-900">Upload File Excel</h3>

        <label class="block">
            <span class="label">Pilih file (.xlsx, .xls, .csv)</span>
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                   class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
            @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-ink-500">Maks. 5 MB. Header wajib ada di baris 1.</p>
        </label>

        <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
            <button class="btn-primary">Mulai Import</button>
            <a href="{{ route('guru-mapel.import.template') }}" class="btn-secondary">Unduh Template</a>
        </div>

        @if(session('importErrors') && count(session('importErrors')))
            <div class="mt-3 p-3 rounded-lg bg-rose-50 border border-rose-200 text-sm">
                <div class="font-semibold text-rose-700 mb-2">{{ count(session('importErrors')) }} baris gagal:</div>
                <ul class="list-disc pl-5 text-rose-600 text-xs space-y-0.5 max-h-48 overflow-auto">
                    @foreach(session('importErrors') as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif
    </form>

    <div class="card card-pad space-y-3 text-sm">
        <h3 class="font-semibold text-ink-900">Format Kolom</h3>
        <code class="block text-[10px] bg-slate-50 p-2 rounded border border-slate-200 break-all">
            nip | kode_mapel | nama_rombel | tahun_ajaran
        </code>
        <ul class="text-xs text-ink-600 list-disc pl-5 space-y-1">
            <li><code>nip</code>: NIP guru — wajib & harus sudah ada di data guru.</li>
            <li><code>kode_mapel</code>: kode mata pelajaran — wajib & harus sudah ada di data mapel.</li>
            <li><code>nama_rombel</code>: nama rombel persis seperti tercatat (mis. <code>X IPA 1</code>) — wajib.</li>
            <li><code>tahun_ajaran</code>: nama TA persis (mis. <code>2024/2025 - Ganjil</code>). Jika <strong>kosong</strong> → otomatis pakai TA aktif.</li>
            <li>1 guru bisa muncul di banyak baris (untuk banyak rombel/mapel).</li>
            <li>Baris yang kombinasinya sudah ada → di-skip (tidak duplikat).</li>
        </ul>
    </div>
</div>
@endsection
