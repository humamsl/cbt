@props(['type' => 'success', 'message' => null, 'duration' => 4000])

@php
    $styles = [
        'success' => ['border-emerald-200 bg-emerald-50', 'text-emerald-800', 'bg-emerald-500', '✓'],
        'error'   => ['border-rose-200 bg-rose-50',       'text-rose-800',    'bg-rose-500',    '✕'],
        'info'    => ['border-sky-200 bg-sky-50',         'text-sky-800',     'bg-sky-500',     'ℹ'],
        'warn'    => ['border-amber-200 bg-amber-50',     'text-amber-800',   'bg-amber-500',   '!'],
    ];
    [$wrap, $text, $dot, $icon] = $styles[$type] ?? $styles['success'];
@endphp

@if($message)
<div x-data="{ show: true }" x-show="show" x-cloak
     x-init="setTimeout(() => show = false, {{ $duration }})"
     x-transition:enter="transition transform ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-3"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="toast {{ $wrap }} {{ $text }}">
    <span class="w-7 h-7 rounded-full text-white {{ $dot }} grid place-items-center font-bold">{!! $icon !!}</span>
    <div class="flex-1">{{ $message }}</div>
    <button @click="show=false" class="text-current/60 hover:text-current text-lg leading-none">×</button>
</div>
@endif
