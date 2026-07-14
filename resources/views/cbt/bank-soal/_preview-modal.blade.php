@php
    /** @var \App\Models\Question $q */
    $typeSlug = strtolower((string) optional($q->type)->slug);
@endphp

<div class="space-y-4">
    {{-- Badges --}}
    <div class="flex flex-wrap gap-2 text-xs">
        <span class="badge-info">{{ optional($q->type)->question_type ?? '-' }}</span>
        <span class="badge-muted">{{ optional($q->mapel)->nama_mapel ?? 'Tanpa mapel' }}</span>
        @if($q->topic)<span class="badge-muted">📂 {{ $q->topic->topic }}</span>@endif
        @if($q->tingkat)<span class="badge-muted">Tingkat {{ $q->tingkat }}</span>@endif
        <span class="badge-{{ ['mudah'=>'success','sedang'=>'warning','sulit'=>'danger'][$q->tingkat_kesulitan] ?? 'muted' }}">
            {{ ucfirst($q->tingkat_kesulitan ?? '-') }}
        </span>
    </div>

    {{-- Judul + soal --}}
    <div>
        <div class="text-xs text-ink-500 uppercase tracking-wide mb-0.5">Judul</div>
        <div class="font-bold text-ink-900">{{ $q->title }}</div>
    </div>

    <div>
        <div class="text-xs text-ink-500 uppercase tracking-wide mb-1">Pertanyaan</div>
        <div class="prose prose-sm max-w-none border border-slate-200 rounded-lg p-3 bg-slate-50/50">
            {!! \App\Support\SoalHtml::render($q->question) !!}
        </div>
    </div>

    {{-- OPSI berdasarkan jenis --}}
    @if(in_array($typeSlug, ['pg', 'pgk', 'benar-salah']))
        <div>
            <div class="text-xs text-ink-500 uppercase tracking-wide mb-1">Opsi Jawaban</div>
            <div class="space-y-1.5">
                @foreach($q->options as $i => $opt)
                    @php $isCorrect = (bool) $opt->is_correct; @endphp
                    <div class="flex items-start gap-2 p-2.5 rounded-lg border {{ $isCorrect ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200' }}">
                        <span class="w-6 h-6 rounded-full grid place-items-center text-xs font-bold shrink-0 {{ $isCorrect ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-700' }}">
                            {{ $opt->label ?: chr(65 + $i) }}
                        </span>
                        <div class="flex-1 text-sm text-ink-800">{!! \App\Support\SoalHtml::render($opt->option_text) !!}</div>
                        @if($isCorrect)
                            <span class="text-emerald-600 text-lg">✓</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

    @elseif($typeSlug === 'fill-blank')
        <div>
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
        <div>
            <div class="text-xs text-ink-500 uppercase tracking-wide mb-1">Pasangan Penjodohan</div>
            @php
                $pairs = $q->options->groupBy('pair_group');
            @endphp
            <div class="space-y-2">
                @foreach($pairs as $group => $opts)
                    @php
                        $left  = $opts->firstWhere('is_left_side', true);
                        $right = $opts->firstWhere('is_left_side', false);
                    @endphp
                    <div class="flex items-center gap-3 p-2 rounded-lg border border-slate-200">
                        <div class="flex-1 text-sm">
                            <span class="text-xs text-ink-500">Kiri:</span>
                            <span class="font-semibold">{!! optional($left)->option_text ?? '-' !!}</span>
                        </div>
                        <span class="text-emerald-500">↔</span>
                        <div class="flex-1 text-sm">
                            <span class="text-xs text-ink-500">Kanan:</span>
                            <span class="font-semibold">{!! optional($right)->option_text ?? '-' !!}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Pembahasan --}}
    @if($q->pembahasan)
        <div>
            <div class="text-xs text-ink-500 uppercase tracking-wide mb-1">Pembahasan</div>
            <div class="prose prose-sm max-w-none border border-amber-200 rounded-lg p-3 bg-amber-50/50">
                {!! $q->pembahasan !!}
            </div>
        </div>
    @endif

    {{-- Meta --}}
    <div class="text-[10px] text-ink-500 pt-2 border-t border-slate-100 flex flex-wrap gap-3">
        <span>ID: #{{ $q->id }}</span>
        <span>Dibuat: {{ $q->created_at?->format('d M Y H:i') ?? '-' }}</span>
        @if($q->updated_at && $q->updated_at->ne($q->created_at))
            <span>Diubah: {{ $q->updated_at->format('d M Y H:i') }}</span>
        @endif
    </div>
</div>
