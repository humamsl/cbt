@php
    /** @var \App\Models\Question $q */
    $typeSlug = strtolower((string) optional($q->type)->slug);
@endphp

<div class="space-y-4 soal-math">
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
    @include('cbt.bank-soal._soal-opsi', ['q' => $q])

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
