@extends('layouts.app')
@section('title', 'Import Bank Soal')
@section('breadcrumb', 'CBT / Bank Soal / Import')

@section('content')
<x-page-header title="Import Bank Soal" subtitle="Upload file Excel (.xlsx) atau Word (.docx) untuk menambahkan soal massal">
    <x-slot:action>
        <a href="{{ route('bank-soal.index') }}" class="btn-secondary">Kembali</a>
    </x-slot:action>
</x-page-header>

<div class="grid lg:grid-cols-2 gap-6">
    {{-- Upload form --}}
    <form method="POST" action="{{ route('bank-soal.import.store') }}" enctype="multipart/form-data"
          class="card card-pad space-y-4">
        @csrf
        <h3 class="font-semibold text-ink-900">Upload File</h3>

        <label class="block">
            <span class="label">Pilih file Excel atau Word</span>
            <input type="file" name="file" accept=".xlsx,.xls,.csv,.docx,.doc" required
                   class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
            @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-ink-500">Maks. 5 MB. Format: .xlsx, .xls, .csv, .docx, .doc</p>
        </label>

        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
            <button class="btn-primary">Mulai Import</button>
            <a href="{{ route('bank-soal.import.template') }}" class="btn-secondary">
                📊 Unduh Template Excel
            </a>
            <a href="{{ route('bank-soal.import.template.word') }}" class="btn-secondary">
                📝 Unduh Template Word
            </a>
        </div>

        @if(session('importErrors') && count(session('importErrors')))
            <div class="mt-3 p-3 rounded-lg bg-rose-50 border border-rose-200 text-sm">
                <div class="font-semibold text-rose-700 mb-2">{{ count(session('importErrors')) }} baris gagal diimport:</div>
                <ul class="list-disc pl-5 text-rose-600 text-xs space-y-0.5 max-h-48 overflow-auto">
                    @foreach(session('importErrors') as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif
    </form>

    {{-- Petunjuk --}}
    <div class="card card-pad space-y-4 text-sm">
        <h3 class="font-semibold text-ink-900">Format File</h3>

        <div>
            <div class="font-semibold mb-1">📊 Excel (.xlsx)</div>
            <p class="text-ink-600 text-xs">Header wajib di baris 1, urutan kolom:</p>
            <code class="block text-[10px] bg-slate-50 p-2 rounded mt-1 border border-slate-200 break-all">
                jenis | mapel_kode | tingkat | judul | pertanyaan | opsi_a | opsi_b | opsi_c | opsi_d | opsi_e | jawaban
            </code>
        </div>

        <div>
            <div class="font-semibold mb-1">📝 Word (.docx)</div>
            <p class="text-ink-600 text-xs">Satu soal = satu blok, dipisah <code>---</code></p>
            <pre class="text-[10px] bg-slate-50 p-2 rounded mt-1 border border-slate-200 whitespace-pre-wrap">#JENIS: pg
#MAPEL: MTK
#JUDUL: Akar 144
#SOAL: Berapa akar dari 144?
A. 10
B. 11
C. 12
D. 13
#JAWABAN: C
---</pre>
        </div>

        <div>
            <div class="font-semibold mb-1">Kode <code>jenis</code></div>
            <ul class="text-xs text-ink-600 space-y-1 pl-4 list-disc">
                <li><code>pg</code> — Pilihan Ganda (jawaban: huruf, mis. <code>C</code>)</li>
                <li><code>pgk</code> — Pilihan Ganda Kompleks (jawaban: huruf dipisah koma, mis. <code>A,C,E</code>)</li>
                <li><code>fill-blank</code> — Fill the Blank (jawaban: teks)</li>
                <li><code>penjodohan</code> — Penjodohan (jawaban: <code>A=val1; B=val2; …</code>)</li>
                <li><code>benar-salah</code> — Benar/Salah (jawaban: <code>B</code> atau <code>S</code>)</li>
            </ul>
        </div>
    </div>
</div>
@endsection
