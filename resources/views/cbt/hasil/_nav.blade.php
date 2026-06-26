@php
    $active = $active ?? 'index';
    $tabs = [
        'index'     => ['Daftar Nilai',     route('hasil.index')],
        'statistik' => ['Statistik Ujian',  route('hasil.statistik')],
        'analisis'  => ['Analisis Butir',   route('hasil.analisis')],
    ];
@endphp
<div class="flex gap-1 bg-white rounded-xl p-1 shadow-soft border border-slate-100 w-fit overflow-x-auto mb-4">
    @foreach($tabs as $key => [$label, $url])
        <a href="{{ $url }}"
           class="px-4 py-2 rounded-lg text-sm font-semibold transition whitespace-nowrap {{ $active === $key ? 'bg-brand-600 text-white shadow-soft' : 'text-ink-600 hover:bg-slate-100' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
