@props(['label', 'value', 'icon' => 'chart', 'tone' => 'brand', 'delta' => null, 'href' => null])

@php
    $tones = [
        'brand'   => ['from-brand-500 to-brand-700',     'text-brand-100',  'ring-brand-200'],
        'emerald' => ['from-emerald-500 to-emerald-700', 'text-emerald-100','ring-emerald-200'],
        'amber'   => ['from-amber-500 to-amber-600',     'text-amber-100',  'ring-amber-200'],
        'rose'    => ['from-rose-500 to-rose-600',       'text-rose-100',   'ring-rose-200'],
        'sky'     => ['from-sky-500 to-sky-700',         'text-sky-100',    'ring-sky-200'],
        'violet'  => ['from-violet-500 to-violet-700',   'text-violet-100', 'ring-violet-200'],
    ];
    [$grad, $iconBg, $ringColor] = $tones[$tone] ?? $tones['brand'];
    $Tag = $href ? 'a' : 'div';
@endphp

<{{ $Tag }} @if($href) href="{{ $href }}" @endif
    class="card card-pad card-hover stat-glow fade-in-up block">
    <div class="flex items-start justify-between">
        <div>
            <div class="text-xs font-semibold text-ink-500 uppercase tracking-wider">{{ $label }}</div>
            <div class="mt-2 text-3xl font-bold text-ink-900 tabular-nums">{{ $value }}</div>
            @if($delta)
                <div class="mt-1.5 text-xs font-semibold text-emerald-600 flex items-center gap-1">
                    <span>↗</span> {{ $delta }}
                </div>
            @endif
        </div>
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br {{ $grad }} text-white grid place-items-center shadow-soft ring-4 ring-white">
            <x-icon :name="$icon" class="w-5 h-5"/>
        </div>
    </div>
</{{ $Tag }}>
