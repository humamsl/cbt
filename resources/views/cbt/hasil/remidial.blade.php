@extends('layouts.app')
@section('title', 'Program Remidial & Pengayaan')
@section('breadcrumb', 'CBT / Hasil / Remidial & Pengayaan')

@section('content')
<x-page-header title="Program Remidial & Pengayaan" subtitle="Siswa di bawah KKM masuk program remidial, siswa tuntas masuk program pengayaan"></x-page-header>

@include('cbt.hasil._nav', ['active' => 'remidial'])

<form class="card card-pad mb-4 grid grid-cols-1 md:grid-cols-3 gap-2">
    <select name="quiz" class="select md:col-span-2" required>
        <option value="">-- Pilih Ujian --</option>
        @foreach($quizzes as $q)
            <option value="{{ $q->id }}" @selected(request('quiz')==$q->id)>
                {{ $q->name }} — {{ optional($q->mapel)->nama_mapel }}
            </option>
        @endforeach
    </select>
    <div class="flex gap-2">
        <input type="number" name="kkm" value="{{ request('kkm', 70) }}" min="0" max="100"
               class="input flex-1" placeholder="KKM (default 70)">
        <button class="btn-primary">Tampilkan</button>
    </div>
</form>

@if(! $quiz)
    <div class="card card-pad text-center text-ink-500">
        Pilih ujian untuk menyusun program remidial &amp; pengayaan.
    </div>
@elseif(empty($data['remidial']) && empty($data['pengayaan']))
    <div class="card card-pad text-center text-ink-500">
        Belum ada attempt selesai pada ujian ini.
    </div>
@else
    {{-- EXPORT WORD — lengkapi detail dokumen sebelum export --}}
    <form method="GET" action="{{ route('hasil.remidial.export') }}" class="card card-pad mb-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="font-semibold text-ink-900">Export Program Remidial &amp; Pengayaan (Word)</h3>
                <p class="text-xs text-ink-500">Format dokumen program remidial dan pengayaan siap cetak (.docx)</p>
            </div>
            <button class="btn-primary">
                <x-icon name="chart" class="w-4 h-4"/> Export Word
            </button>
        </div>
        <input type="hidden" name="quiz" value="{{ $quiz->id }}">
        <input type="hidden" name="kkm" value="{{ $kkm }}">
        <div class="grid md:grid-cols-3 gap-2">
            <input type="text" name="ulangan_ke" value="{{ request('ulangan_ke') }}"
                   class="input" placeholder="Ulangan Harian ke (mis. 3)">
            <input type="date" name="tanggal_ulangan"
                   value="{{ request('tanggal_ulangan', optional($quiz->valid_from)->format('Y-m-d')) }}"
                   class="input" title="Tanggal Ulangan Harian">
            <input type="text" name="bentuk_soal" value="{{ request('bentuk_soal', 'Pilihan Ganda') }}"
                   class="input" placeholder="Bentuk Soal UH">
            <input type="text" name="materi_kd" value="{{ request('materi_kd') }}"
                   class="input md:col-span-2" placeholder="Materi (KD / Indikator), mis. 3.3 Mendeskripsikan relasi dan fungsi">
            <input type="date" name="rencana_ulangan_rem" value="{{ request('rencana_ulangan_rem') }}"
                   class="input" title="Rencana Ulangan Remidial">
            <select name="semester" class="select">
                <option value="">-- Semester (otomatis) --</option>
                <option value="Ganjil" @selected(request('semester')==='Ganjil')>Ganjil</option>
                <option value="Genap" @selected(request('semester')==='Genap')>Genap</option>
            </select>
            <input type="text" name="kelas_semester" value="{{ request('kelas_semester') }}"
                   class="input md:col-span-2" placeholder="Kelas / Semester (kosongkan = otomatis)">
            <input type="text" name="bentuk_remidial" value="{{ request('bentuk_remidial') }}"
                   class="input md:col-span-3" placeholder="Bentuk Pelaksanaan Remidial (kosongkan = otomatis: {{ $data['bentuk_remidial_default'] }})">
            <textarea name="bentuk_pengayaan" rows="2" class="input md:col-span-3"
                      placeholder="Bentuk Pengayaan (kosongkan = otomatis: {{ $data['bentuk_pengayaan_default'] }})">{{ request('bentuk_pengayaan') }}</textarea>
        </div>
    </form>

    {{-- RINGKASAN --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        @php
            $totalSiswa = count($data['remidial']) + count($data['pengayaan']);
            $cards = [
                ['Peserta', $totalSiswa, 'brand'],
                ['Remidial (< '.$kkm.')', count($data['remidial']), 'rose'],
                ['Pengayaan (Tuntas)', count($data['pengayaan']), 'emerald'],
                ['% Remidial', number_format($data['persen_remidial'], 1).'%', 'amber'],
            ];
        @endphp
        @foreach($cards as $c)
            <div class="card card-pad text-center">
                <div class="text-xs text-ink-500 uppercase tracking-wide">{{ $c[0] }}</div>
                <div class="text-2xl font-bold text-{{ $c[2] }}-600 mt-1">{{ $c[1] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        {{-- TABEL REMIDIAL --}}
        <div class="card table-wrap">
            <div class="px-4 pt-4 pb-2">
                <h3 class="font-semibold text-ink-900">Program Remidial</h3>
                <p class="text-xs text-ink-500">Nilai di bawah KKM {{ $kkm }} — bentuk pelaksanaan: {{ $data['bentuk_remidial_default'] }}</p>
            </div>
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>No</th><th>Nama Siswa</th>
                        <th class="text-center">Nilai</th>
                        <th>Nomor Soal Salah / Tidak Dikuasai</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($data['remidial'] as $i => $s)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            <div class="font-semibold text-ink-900">{{ $s['nama'] }}</div>
                            <div class="text-xs text-ink-500 font-mono">{{ $s['nisn'] }}</div>
                        </td>
                        <td class="text-center"><span class="badge-danger">{{ $s['nilai'] }}</span></td>
                        <td class="text-xs font-mono">{{ $s['soal_salah'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-6 text-ink-500">Tidak ada siswa remidial — semua tuntas 🎉</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- TABEL PENGAYAAN --}}
        <div class="card table-wrap">
            <div class="px-4 pt-4 pb-2">
                <h3 class="font-semibold text-ink-900">Program Pengayaan</h3>
                <p class="text-xs text-ink-500">Nilai mencapai / melampaui KKM {{ $kkm }}</p>
            </div>
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>No</th><th>Nama Siswa</th>
                        <th class="text-center">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($data['pengayaan'] as $i => $s)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            <div class="font-semibold text-ink-900">{{ $s['nama'] }}</div>
                            <div class="text-xs text-ink-500 font-mono">{{ $s['nisn'] }}</div>
                        </td>
                        <td class="text-center"><span class="badge-success">{{ $s['nilai'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center py-6 text-ink-500">Belum ada siswa yang mencapai KKM.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
