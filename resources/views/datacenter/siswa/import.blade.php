@extends('layouts.app')
@section('title', 'Import Data Siswa')
@section('breadcrumb', 'Data Center / Siswa / Import')

@section('content')
<x-page-header title="Import Data Siswa" subtitle="Upload file Excel (.xlsx) untuk menambah / memperbarui siswa massal">
    <x-slot:action>
        <a href="{{ route('siswa.index') }}" class="btn-secondary">Kembali</a>
    </x-slot:action>
</x-page-header>

<div class="grid lg:grid-cols-2 gap-6">
    <form method="POST" action="{{ route('siswa.import.store') }}" enctype="multipart/form-data"
          class="card card-pad space-y-4">
        @csrf
        <h3 class="font-semibold text-ink-900">Upload File Excel</h3>

        <label class="block">
            <span class="label">Pilih file (.xlsx, .xls, .csv)</span>
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                   class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
            @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-ink-500">Maks. 10 MB. Header wajib ada di baris 1.</p>
        </label>

        <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
            <button class="btn-primary">Mulai Import</button>
            <a href="{{ route('siswa.import.template') }}" class="btn-secondary">Unduh Template</a>
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
            nisn | nis | nama_siswa | jenis_kelamin | tempat_lahir | tanggal_lahir | agama | alamat | nomor_hp | email | nama_ayah | nama_ibu | nomor_hp_ortu | rombel | password | is_aktif
        </code>
        <ul class="text-xs text-ink-600 list-disc pl-5 space-y-1">
            <li>nisn dan nama_siswa wajib diisi</li>
            <li><code>nisn</code> dipakai sebagai kunci unik. Jika sudah ada → di-update; jika belum → dibuat baru.</li>
            <li><code>jenis_kelamin</code>: <code>L</code> atau <code>P</code></li>
            <li><code>tanggal_lahir</code>: <code>YYYY-MM-DD</code> atau format tanggal Excel</li>
            <li><code>rombel</code>: nama rombel persis di Tahun Ajaran <strong>aktif</strong> (mis. <code>X IPA 1</code>). Akan otomatis dipasang.</li>
            <li><code>password</code> opsional. Jika kosong & akun baru, default = NISN.</li>
            <li><code>is_aktif</code>: 1 atau 0 (jika kosong = 1)</li>
        </ul>
    </div>
</div>
@endsection
