@extends('layouts.app')
@section('title', 'Statistik Ujian')
@section('breadcrumb', 'CBT / Hasil / Statistik')

@section('content')
<x-page-header title="Statistik Nilai Ujian" subtitle="Ringkasan distribusi nilai per ujian"></x-page-header>

@include('cbt.hasil._nav', ['active' => 'statistik'])

<form class="card card-pad mb-4 grid md:grid-cols-3 gap-2">
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
        Pilih ujian untuk melihat statistik.
    </div>
@else
    {{-- EXPORT "HASIL NILAI TES" — lengkapi detail lembar cetak sebelum export --}}
    <form method="GET" action="{{ route('hasil.statistik.export') }}" class="card card-pad mb-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-ink-900">Export Hasil Nilai Tes (Excel)</h3>
            <button class="btn-primary">
                <x-icon name="chart" class="w-4 h-4"/> Export
            </button>
        </div>
        <input type="hidden" name="quiz" value="{{ $quiz->id }}">
        <div class="grid md:grid-cols-3 gap-2">
            <input type="text" name="nama_tes" value="{{ request('nama_tes', $quiz->name) }}"
                   class="input" placeholder="Nama Tes (mis. Uraian)">
            <input type="text" name="materi_pokok" value="{{ request('materi_pokok') }}"
                   class="input" placeholder="Materi Pokok">
            <select name="semester" class="select">
                <option value="">-- Semester --</option>
                <option value="Ganjil" @selected(request('semester')==='Ganjil')>Ganjil</option>
                <option value="Genap" @selected(request('semester')==='Genap')>Genap</option>
            </select>
            <input type="text" name="tujuan_pembelajaran" value="{{ request('tujuan_pembelajaran') }}"
                   class="input md:col-span-2" placeholder="Tujuan Pembelajaran">
            <input type="date" name="tanggal_tes"
                   value="{{ request('tanggal_tes', optional($quiz->valid_from)->format('Y-m-d')) }}"
                   class="input">
            <input type="number" name="kktp" value="{{ request('kktp', request('kkm', 70)) }}" min="0" max="100"
                   class="input" placeholder="KKTP (default 70)">
            <input type="text" name="kelas_semester_tahun" value="{{ request('kelas_semester_tahun') }}"
                   class="input md:col-span-2" placeholder="Kelas/Semester/Tahun (kosongkan = otomatis)">
        </div>
    </form>

    {{-- HEADER KARTU INFO --}}
    <div class="card card-pad mb-4 flex flex-wrap gap-4 items-center justify-between">
        <div>
            <div class="text-xs text-ink-500">Ujian</div>
            <div class="text-lg font-bold text-ink-900">{{ $quiz->name }}</div>
            <div class="text-sm text-ink-600">
                {{ optional($quiz->mapel)->nama_mapel ?? '-' }} ·
                {{ $stats['total_soal'] }} soal ·
                KKM: {{ $stats['kkm'] }}
            </div>
        </div>
        <div class="text-right">
            <div class="text-xs text-ink-500">Peserta Selesai</div>
            <div class="text-2xl font-bold text-brand-600">
                {{ $stats['peserta_selesai'] }} / {{ $stats['total_peserta'] }}
            </div>
        </div>
    </div>

    {{-- METRIC CARDS --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        @php
            $cards = [
                ['Rata-rata',    number_format($stats['mean'], 1),   'emerald'],
                ['Median',       number_format($stats['median'], 1), 'sky'],
                ['Tertinggi',    number_format($stats['max'], 1),    'brand'],
                ['Terendah',     number_format($stats['min'], 1),    'rose'],
                ['Std Deviasi',  number_format($stats['stddev'], 2), 'amber'],
                ['% Lulus',      number_format($stats['pass_rate'], 1).'%', 'emerald'],
            ];
        @endphp
        @foreach($cards as $c)
            <div class="card card-pad text-center">
                <div class="text-xs text-ink-500 uppercase tracking-wide">{{ $c[0] }}</div>
                <div class="text-2xl font-bold text-{{ $c[2] }}-600 mt-1">{{ $c[1] }}</div>
            </div>
        @endforeach
    </div>

    {{-- DISTRIBUSI NILAI (bar lama, dipertahankan) --}}
    <div class="card card-pad mb-4">
        <h3 class="font-semibold text-ink-900 mb-3">Distribusi Nilai (Bar)</h3>
        @php $maxCount = max(array_values($stats['distribution']) ?: [1]); @endphp
        <div class="space-y-2">
            @foreach($stats['distribution'] as $range => $count)
                @php $pct = $maxCount > 0 ? ($count / $maxCount * 100) : 0; @endphp
                <div class="flex items-center gap-3">
                    <div class="w-20 text-xs font-mono text-ink-600 text-right">{{ $range }}</div>
                    <div class="flex-1 bg-slate-100 rounded-full h-6 relative overflow-hidden">
                        @php
                            $color = match (true) {
                                str_contains($range, '0-49') => 'bg-rose-500',
                                str_contains($range, '50-59') => 'bg-orange-500',
                                str_contains($range, '60-69') => 'bg-amber-500',
                                str_contains($range, '70-79') => 'bg-lime-500',
                                str_contains($range, '80-89') => 'bg-emerald-500',
                                default => 'bg-brand-600',
                            };
                        @endphp
                        <div class="{{ $color }} h-full rounded-full transition-all"
                             style="width: {{ $pct }}%"></div>
                        <div class="absolute inset-0 grid place-items-center text-xs font-semibold text-ink-900">
                            {{ $count }} siswa
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- GRID: Tabel per-soal (kiri) + 2 donut chart (kanan) --}}
    <div class="grid lg:grid-cols-3 gap-4 mb-4">
        {{-- HASIL PENGERJAAN TIAP SOAL --}}
        <div class="card card-pad lg:col-span-2"
             x-data="perSoalTable({
                rows: @js($stats['per_soal']),
                perPage: 8
             })">
            <h3 class="font-semibold text-ink-900 mb-3 uppercase tracking-wide">Hasil Pengerjaan Tiap Soal</h3>

            <div class="flex items-center justify-between text-xs text-ink-600 mb-2">
                <div>
                    Showing <span x-text="from"></span> to <span x-text="to"></span>
                    of <span x-text="rows.length"></span> results
                </div>
                <div class="flex items-center gap-1">
                    <button @click="prev()" :disabled="page===1"
                            class="btn-ghost p-1.5 disabled:opacity-30">‹</button>
                    <template x-for="p in pages" :key="p">
                        <button @click="page = p"
                                :class="page === p ? 'bg-brand-600 text-white' : 'hover:bg-slate-100'"
                                class="w-7 h-7 rounded text-xs font-semibold transition"
                                x-text="p"></button>
                    </template>
                    <button @click="next()" :disabled="page===totalPages"
                            class="btn-ghost p-1.5 disabled:opacity-30">›</button>
                </div>
            </div>

            <table class="table-modern">
                <thead class="bg-slate-700 text-white">
                    <tr>
                        <th class="text-white">No.</th>
                        <th class="text-white">Soal</th>
                        <th class="text-white text-center">Benar</th>
                        <th class="text-white text-center">Salah</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in visibleRows" :key="row.no">
                        <tr>
                            <td x-text="row.no + '.'"></td>
                            <td class="font-medium" x-text="row.no"></td>
                            <td class="text-center font-semibold text-emerald-600" x-text="row.benar"></td>
                            <td class="text-center font-semibold text-rose-600" x-text="row.salah"></td>
                        </tr>
                    </template>
                    <template x-if="rows.length === 0">
                        <tr><td colspan="4" class="text-center py-6 text-ink-500">Belum ada data soal.</td></tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- DONUT: Status Pengerjaan --}}
        <div class="space-y-4">
            <div class="card card-pad">
                <h3 class="font-semibold text-ink-900 mb-3 uppercase tracking-wide">Grafik Status Pengerjaan</h3>
                <div class="flex items-center gap-3 text-xs mb-3">
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-500"></span> Sudah Mengerjakan</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-orange-500"></span> Belum Mengerjakan</span>
                </div>
                <div class="relative h-56">
                    <canvas id="chart-status"></canvas>
                </div>
                <div class="text-center text-xs text-ink-500 mt-2">
                    {{ $stats['status_pengerjaan']['sudah'] }} / {{ $stats['status_pengerjaan']['target'] ?: '—' }} siswa selesai
                </div>
            </div>

            <div class="card card-pad">
                <h3 class="font-semibold text-ink-900 mb-3 uppercase tracking-wide">Grafik Nilai Siswa</h3>
                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs mb-3">
                    @foreach(['Sempurna' => 'emerald','Diatas 90' => 'lime','Diatas 80' => 'yellow','Diatas 70' => 'amber','Diatas 60' => 'orange','Diatas 50' => 'red','Dibawah 50' => 'rose'] as $label => $color)
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded bg-{{ $color }}-500"></span> {{ $label }}
                        </span>
                    @endforeach
                </div>
                <div class="relative h-56">
                    <canvas id="chart-nilai"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Chart.js (self-hosted via npm + Vite) + Alpine helper --}}
    @vite(['resources/js/charts.js'])
    <script>
        function perSoalTable(cfg) {
            return {
                rows: cfg.rows,
                perPage: cfg.perPage,
                page: 1,
                get totalPages() { return Math.max(1, Math.ceil(this.rows.length / this.perPage)); },
                get from() { return this.rows.length ? (this.page - 1) * this.perPage + 1 : 0; },
                get to()   { return Math.min(this.page * this.perPage, this.rows.length); },
                get visibleRows() { return this.rows.slice((this.page - 1) * this.perPage, this.page * this.perPage); },
                get pages() {
                    const arr = [];
                    const max = Math.min(this.totalPages, 7);
                    for (let i = 1; i <= max; i++) arr.push(i);
                    return arr;
                },
                prev() { if (this.page > 1) this.page--; },
                next() { if (this.page < this.totalPages) this.page++; },
            };
        }
        window.perSoalTable = perSoalTable;

        document.addEventListener('DOMContentLoaded', () => {
            // Donut: Status Pengerjaan
            const statusEl = document.getElementById('chart-status');
            if (statusEl && window.Chart) {
                new Chart(statusEl, {
                    type: 'doughnut',
                    data: {
                        labels: ['Sudah Mengerjakan', 'Belum Mengerjakan'],
                        datasets: [{
                            data: [{{ $stats['status_pengerjaan']['sudah'] }}, {{ $stats['status_pengerjaan']['belum'] }}],
                            backgroundColor: ['#10b981', '#f97316'],
                            borderWidth: 2,
                            borderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        cutout: '60%',
                    }
                });
            }

            // Donut: Grafik Nilai
            const nilaiEl = document.getElementById('chart-nilai');
            if (nilaiEl && window.Chart) {
                new Chart(nilaiEl, {
                    type: 'doughnut',
                    data: {
                        labels: @json(array_keys($stats['nilai_buckets'])),
                        datasets: [{
                            data: @json(array_values($stats['nilai_buckets'])),
                            backgroundColor: [
                                '#059669', // Sempurna emerald
                                '#84cc16', // Diatas 90 lime
                                '#eab308', // Diatas 80 yellow
                                '#f59e0b', // Diatas 70 amber
                                '#f97316', // Diatas 60 orange
                                '#ef4444', // Diatas 50 red
                                '#e11d48', // Dibawah 50 rose
                            ],
                            borderWidth: 2,
                            borderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        cutout: '60%',
                    }
                });
            }
        });
    </script>
@endif
@endsection
