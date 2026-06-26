@props(['title', 'subtitle' => null, 'action' => null])

<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <h2 class="text-xl font-bold text-ink-900">{{ $title }}</h2>
        @if($subtitle)<p class="text-sm text-ink-500 mt-0.5">{{ $subtitle }}</p>@endif
    </div>
    @if($action)<div class="flex items-center gap-2">{{ $action }}</div>@endif
</div>
