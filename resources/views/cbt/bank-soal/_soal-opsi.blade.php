@php
    /** @var \App\Models\Question $q */
    $typeSlug = strtolower((string) optional($q->type)->slug);
@endphp

@if(in_array($typeSlug, ['pg', 'pgk', 'benar-salah']))
    <div class="mt-2">
        <div class="text-xs text-ink-500 uppercase tracking-wide mb-1">Opsi Jawaban</div>
        <div class="space-y-1.5">
            @foreach($q->options as $i => $opt)
                @php $isCorrect = (bool) $opt->is_correct; @endphp
                <div class="flex items-start gap-2 p-2 rounded-lg border text-sm {{ $isCorrect ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200' }}">
                    <span class="w-6 h-6 rounded-full grid place-items-center text-xs font-bold shrink-0 {{ $isCorrect ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-700' }}">
                        {{ $opt->label ?: chr(65 + $i) }}
                    </span>
                    <div class="flex-1 text-ink-800 soal-math">{!! \App\Support\SoalHtml::render($opt->option_text) !!}</div>
                    @if($isCorrect)
                        <span class="text-emerald-600 text-lg">✓</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

@elseif($typeSlug === 'fill-blank')
    <div class="mt-2">
        <div class="text-xs text-ink-500 uppercase tracking-wide mb-1">Kunci Jawaban</div>
        <div class="p-3 rounded-lg border border-emerald-400 bg-emerald-50">
            @php $jawabans = array_filter(array_map('trim', explode('|', (string) $q->correct_answer_text))); @endphp
            @if(empty($jawabans))
                <span class="text-ink-500 italic">— belum diisi —</span>
            @else
                <div class="flex flex-wrap gap-1.5">
                    @foreach($jawabans as $j)
                        <span class="px-2 py-0.5 rounded bg-emerald-200 text-emerald-900 text-sm font-mono">{{ $j }}</span>
                    @endforeach
                </div>
                @if(count($jawabans) > 1)
                    <div class="text-[10px] text-emerald-700 mt-1">Multi-jawaban (siswa jawab salah satu = benar)</div>
                @endif
            @endif
            <div class="text-[10px] text-ink-600 mt-1.5">
                Sensitif huruf besar/kecil: <strong>{{ $q->case_sensitive ? 'YA' : 'TIDAK' }}</strong>
            </div>
        </div>
    </div>

@elseif($typeSlug === 'penjodohan')
    <div class="mt-2">
        <div class="text-xs text-ink-500 uppercase tracking-wide mb-1">Pasangan Penjodohan</div>
        @php $pairs = $q->options->groupBy('pair_group'); @endphp
        <div class="space-y-2">
            @foreach($pairs as $group => $opts)
                @php
                    $left  = $opts->firstWhere('is_left_side', true);
                    $right = $opts->firstWhere('is_left_side', false);
                @endphp
                <div class="flex items-center gap-3 p-2 rounded-lg border border-slate-200 text-sm">
                    <div class="flex-1">
                        <span class="text-xs text-ink-500">Kiri:</span>
                        <span class="font-semibold">{!! optional($left)->option_text ?? '-' !!}</span>
                    </div>
                    <span class="text-emerald-500">↔</span>
                    <div class="flex-1">
                        <span class="text-xs text-ink-500">Kanan:</span>
                        <span class="font-semibold">{!! optional($right)->option_text ?? '-' !!}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
