<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; }
        h1 { font-size: 16pt; margin: 0 0 4px; }
        .meta { color: #555; font-size: 9pt; margin-bottom: 14px; }
        .soal { margin: 0 0 14px; page-break-inside: avoid; border-bottom: 1px dashed #ccc; padding-bottom: 8px; }
        .judul { font-weight: bold; }
        .tag { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 8pt; margin-right: 4px; background: #eef; color: #225; }
        .opsi { margin-left: 18px; }
        .opsi li { margin: 2px 0; }
        .kunci { color: #0a7; font-weight: bold; }
        table.pj { border-collapse: collapse; margin-top: 4px; }
        table.pj td { border: 1px solid #ddd; padding: 4px 8px; font-size: 10pt; }
    </style>
</head>
<body>

<h1>{{ $title }}</h1>
<div class="meta">
    Total {{ $questions->count() }} soal &middot; Dicetak {{ now()->translatedFormat('d F Y H:i') }}
</div>

@foreach($questions as $idx => $q)
    @php $jenis = optional($q->type)->slug ?? 'pg'; @endphp
    <div class="soal">
        <div>
            <span class="tag">{{ optional($q->type)->question_type ?? 'Soal' }}</span>
            <span class="tag">{{ optional($q->mapel)->nama_mapel ?? '-' }}</span>
        </div>
        <p><strong>{{ $idx + 1 }}.</strong> {!! strip_tags($q->question, '<br><strong><em><u>') !!}</p>

        @if(in_array($jenis, ['pg', 'pgk']))
            <ol type="A" class="opsi">
                @foreach($q->options as $opt)
                    <li @if($withAnswer && $opt->is_correct) class="kunci" @endif>
                        {{ $opt->option_text }} @if($withAnswer && $opt->is_correct) ✓ @endif
                    </li>
                @endforeach
            </ol>
        @elseif($jenis === 'benar-salah')
            <div class="opsi">
                ( ) Benar &nbsp;&nbsp;&nbsp; ( ) Salah
                @if($withAnswer)
                    <div class="kunci">Kunci: {{ optional($q->options->firstWhere('is_correct', true))->option_text }}</div>
                @endif
            </div>
        @elseif($jenis === 'fill-blank')
            <div class="opsi">Jawaban: ______________________________</div>
            @if($withAnswer)<div class="kunci">Kunci: {{ $q->correct_answer_text }}</div>@endif
        @elseif($jenis === 'penjodohan')
            @php
                $kiri = $q->options->where('is_left_side', true)->values();
                $kanan = $q->options->where('is_left_side', false)->shuffle()->values();
            @endphp
            <table class="pj">
                <tr><th>Kiri</th><th>Kanan (acak)</th></tr>
                @for($i = 0; $i < max(count($kiri), count($kanan)); $i++)
                    <tr>
                        <td>{{ chr(65 + $i) }}. {{ $kiri[$i]->option_text ?? '' }}</td>
                        <td>{{ $i + 1 }}. {{ $kanan[$i]->option_text ?? '' }}</td>
                    </tr>
                @endfor
            </table>
            @if($withAnswer)
                <div class="kunci">Kunci:
                    @php
                        $kananByPair = $q->options->where('is_left_side', false)->keyBy('pair_group');
                        $pairs = [];
                        foreach ($kiri as $i => $opt) {
                            if (isset($kananByPair[$opt->pair_group])) {
                                $pairs[] = chr(65 + $i).'='.$kananByPair[$opt->pair_group]->option_text;
                            }
                        }
                        echo implode('; ', $pairs);
                    @endphp
                </div>
            @endif
        @endif

    </div>
@endforeach

</body>
</html>
