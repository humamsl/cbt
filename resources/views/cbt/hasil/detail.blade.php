@extends('layouts.app')
@section('title', 'Detail Hasil')

@section('content')
<x-page-header :title="'Detail Hasil: '.($attempt->siswa->nama_siswa ?? '-')"
               :subtitle="$attempt->quiz->name"/>

<div class="grid md:grid-cols-{{ $attempt->partial_count > 0 ? 5 : 4 }} gap-4 mb-6">
    <x-stat-card label="Nilai" :value="number_format($attempt->nilai ?? 0, 1)" icon="chart" tone="brand"/>
    <x-stat-card label="Benar" :value="$attempt->correct_count" icon="check" tone="emerald"/>
    @if($attempt->partial_count > 0)
        <x-stat-card label="Sebagian Benar" :value="$attempt->partial_count" icon="document" tone="amber"/>
    @endif
    <x-stat-card label="Salah" :value="$attempt->wrong_count" icon="trash" tone="rose"/>
    <x-stat-card label="Kosong" :value="$attempt->empty_count" icon="document" tone="sky"/>
</div>

<div class="card">
    <div class="card-header"><h3 class="text-base font-semibold">Detail Jawaban</h3></div>
    <ul class="divide-y divide-slate-100">
    @foreach($attempt->answers as $idx => $a)
        @php
            $q = optional($a->quizQuestion)->question;
        @endphp
        @if(! $q)
            {{-- Soal induknya sudah terhapus/hilang dari database (quiz_questions
                 atau questions-nya) SETELAH siswa menjawabnya -- lihat catatan di
                 BankSoalController::assertSoalTidakSedangDipakai(). Placeholder di
                 sini supaya satu soal rusak tidak menggagalkan seluruh halaman
                 review jawaban attempt ini. --}}
            <li class="px-4 sm:px-6 py-4">
                <div class="font-semibold text-ink-900 mb-1">{{ $idx + 1 }}. <span class="text-rose-600">⚠ Soal tidak dapat dimuat</span></div>
                <div class="text-xs text-ink-500">Soal ini sudah terhapus dari bank soal setelah dijawab siswa.</div>
            </li>
        @else
        @php
            $typeSlug = strtolower((string) (optional($q->type)->slug ?? optional($q->type)->question_type ?? ''));
            $isPgk = $typeSlug === 'pgk';
            $isPenjodohan = $typeSlug === 'penjodohan';
            // PGK/Penjodohan bisa punya >1 opsi/pasangan benar -- tampilkan
            // semuanya, bukan cuma firstWhere() (itu sebabnya dulu PGK selalu
            // dinilai salah kecuali siswa pilih opsi benar yang urutannya
            // paling awal di database).
            $correctOptions = $isPgk
                ? $q->options->where('is_correct', true)
                : collect([$q->options->firstWhere('is_correct', true)])->filter();
            $selectedOptions = $isPgk
                ? $q->options->whereIn('id', $a->selectedOptionIds())
                : collect([$a->option])->filter();

            // Rincian bobot nilai -- partial_score (0.0-1.0) tersimpan untuk
            // SEMUA jenis soal (lihat UjianController::finalize()), jadi poin
            // didapat bisa dihitung seragam: pecahan x bobot soal. Untuk
            // PGK/Penjodohan pecahan itu sendiri dijelaskan lebih rinci di
            // bawah supaya persentase "Sebagian Benar" tidak terasa ajaib.
            $marks = (float) ($a->quizQuestion->marks ?? 0);
            $poinDidapat = (float) ($a->partial_score ?? ($a->is_correct ? 1 : 0)) * $marks;

            $pgkBenarDipilih = $isPgk ? $selectedOptions->pluck('id')->intersect($correctOptions->pluck('id'))->count() : 0;
            $pgkSalahDipilih = $isPgk ? $selectedOptions->pluck('id')->diff($correctOptions->pluck('id'))->count() : 0;
            $pgkTotalBenar   = $isPgk ? $correctOptions->count() : 0;

            if ($isPenjodohan) {
                $penjodohanLeft = $q->options->where('is_left_side', true);
                $penjodohanRightByGroup = $q->options->where('is_left_side', false)->keyBy('pair_group');
                $penjodohanStudentPairs = $a->matchPairs();
                $penjodohanBenar = $penjodohanLeft->filter(function ($left) use ($penjodohanRightByGroup, $penjodohanStudentPairs) {
                    $chosenId = $penjodohanStudentPairs[$left->id] ?? null;
                    $expected = $penjodohanRightByGroup[$left->pair_group] ?? null;
                    return $chosenId && $expected && (int) $chosenId === (int) $expected->id;
                })->count();
                $penjodohanTotal = $penjodohanLeft->count();
            }
        @endphp
        <li class="px-4 sm:px-6 py-4 soal-math">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div class="font-semibold text-ink-900">{{ $idx + 1 }}. {{ $q->title }}</div>
                @if($a->is_correct)<span class="badge-success">Benar</span>
                @elseif(($isPgk || $isPenjodohan) && $a->partial_score > 0)<span class="badge-warning">Sebagian Benar ({{ round($a->partial_score * 100) }}%)</span>
                @elseif($a->is_correct === false)<span class="badge-danger">Salah</span>
                @else<span class="badge-muted">-</span>@endif
            </div>

            {{-- Rincian bobot nilai -- supaya persentase "Sebagian Benar" (PGK/
                 Penjodohan) tidak terasa ajaib, dan tiap jenis soal terlihat
                 jelas berapa poin yang didapat dari berapa poin bobotnya. --}}
            <div class="text-xs text-ink-500 mb-2">
                Bobot soal: <span class="font-semibold text-ink-700">{{ rtrim(rtrim(number_format($marks, 2), '0'), '.') }} poin</span>
                &middot; Diperoleh: <span class="font-semibold {{ $poinDidapat >= $marks ? 'text-emerald-600' : ($poinDidapat > 0 ? 'text-amber-600' : 'text-rose-600') }}">{{ rtrim(rtrim(number_format($poinDidapat, 2), '0'), '.') }} poin</span>
                @if($isPgk)
                    &middot; {{ $pgkBenarDipilih }} opsi benar dipilih @if($pgkSalahDipilih > 0)− {{ $pgkSalahDipilih }} opsi salah dipilih @endif dari {{ $pgkTotalBenar }} total opsi benar
                    <span class="text-ink-400">({{ $pgkBenarDipilih }}@if($pgkSalahDipilih > 0)−{{ $pgkSalahDipilih }}@endif)/{{ $pgkTotalBenar }} = {{ round((($pgkBenarDipilih - $pgkSalahDipilih) / max($pgkTotalBenar, 1)) * 100) }}%</span>
                @elseif($isPenjodohan)
                    &middot; {{ $penjodohanBenar }} dari {{ $penjodohanTotal }} pasangan dicocokkan dengan benar
                    <span class="text-ink-400">({{ $penjodohanBenar }}/{{ $penjodohanTotal }} = {{ $penjodohanTotal ? round($penjodohanBenar / $penjodohanTotal * 100) : 0 }}%)</span>
                @endif
            </div>

            <div class="text-sm text-ink-600 mb-2 prose prose-sm max-w-none">{!! $q->question !!}</div>

            @if($isPenjodohan)
                @php
                    $leftOptions = $q->options->where('is_left_side', true)->sortBy('order');
                    $rightByPairGroup = $q->options->where('is_left_side', false)->keyBy('pair_group');
                    $rightById = $q->options->where('is_left_side', false)->keyBy('id');
                    $studentPairs = $a->matchPairs();
                @endphp
                <div class="space-y-1.5 text-sm">
                    @foreach($leftOptions as $left)
                        @php
                            $chosenId = $studentPairs[$left->id] ?? null;
                            $chosenOpt = $chosenId ? ($rightById[$chosenId] ?? null) : null;
                            $correctOpt = $rightByPairGroup[$left->pair_group] ?? null;
                            $ok = $chosenOpt && $correctOpt && $chosenOpt->id === $correctOpt->id;
                        @endphp
                        <div class="flex flex-wrap items-center gap-2 p-2 rounded-lg border {{ $ok ? 'border-emerald-300 bg-emerald-50' : 'border-rose-200 bg-rose-50' }}">
                            <span class="font-medium">{{ $left->option_text }}</span>
                            <span class="text-ink-400">→</span>
                            <span>{{ $chosenOpt->option_text ?? '— belum dijawab —' }}</span>
                            @if($ok)
                                <span class="text-emerald-600 ml-auto">✓</span>
                            @else
                                <span class="text-xs text-emerald-700 ml-auto">Seharusnya: {{ optional($correctOpt)->option_text ?? '-' }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="grid sm:grid-cols-2 gap-2 text-sm">
                    <div class="p-2 rounded-lg bg-slate-50 border border-slate-100">
                        <div class="text-xs text-ink-500">Jawaban siswa</div>
                        <div class="prose prose-sm max-w-none space-y-1">
                            @forelse($selectedOptions as $opt)
                                <div>{!! $opt->option_text !!}</div>
                            @empty
                                {{-- Jawaban isian = teks bebas dari siswa: WAJIB di-escape ({{ }}),
                                     dan diuji dengan filled() -- bukan `?:` -- karena jawaban "0"
                                     dianggap falsy oleh PHP dan tampil "kosong" padahal tersimpan. --}}
                                @if(filled($a->answer_text))
                                    {{ $a->answer_text }}
                                @else
                                    — kosong —
                                @endif
                            @endforelse
                        </div>
                    </div>
                    <div class="p-2 rounded-lg bg-emerald-50 border border-emerald-100">
                        <div class="text-xs text-emerald-700">Kunci</div>
                        <div class="prose prose-sm max-w-none space-y-1">
                            @if($typeSlug === 'fill-blank' || $typeSlug === 'fill_blank' || str_contains($typeSlug, 'fill'))
                                {{-- Fill-blank tidak punya question_options -- kuncinya di
                                     correct_answer_text (bisa multi-jawaban dipisah "|"). --}}
                                @forelse(array_filter(array_map('trim', explode('|', (string) $q->correct_answer_text))) as $jawaban)
                                    <div>{{ $jawaban }}</div>
                                @empty
                                    -
                                @endforelse
                            @else
                                @forelse($correctOptions as $opt)
                                    <div>{!! $opt->option_text !!}</div>
                                @empty
                                    -
                                @endforelse
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </li>
        @endif
    @endforeach
    </ul>
</div>
@endsection
