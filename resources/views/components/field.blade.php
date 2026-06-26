@props(['label' => null, 'name', 'type' => 'text', 'value' => null, 'required' => false, 'placeholder' => null, 'options' => null, 'help' => null])

<div>
    @if($label)<label class="label" for="{{ $name }}">{{ $label }} @if($required)<span class="text-rose-500">*</span>@endif</label>@endif

    @if($type === 'textarea')
        <textarea name="{{ $name }}" id="{{ $name }}" rows="3" {{ $attributes->merge(['class' => 'input']) }} placeholder="{{ $placeholder }}">{{ old($name, $value) }}</textarea>
    @elseif($type === 'select')
        <select name="{{ $name }}" id="{{ $name }}" {{ $attributes->merge(['class' => 'select']) }}>
            <option value="">— Pilih —</option>
            @foreach(($options ?? []) as $val => $lbl)
                <option value="{{ $val }}" @selected(old($name, $value) == $val)>{{ $lbl }}</option>
            @endforeach
        </select>
    @elseif($type === 'checkbox')
        <label class="inline-flex items-center gap-2 text-sm text-ink-700">
            <input type="hidden" name="{{ $name }}" value="0">
            <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $value))
                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ $help ?? 'Aktif' }}
        </label>
    @else
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
               value="{{ old($name, $value) }}"
               placeholder="{{ $placeholder }}"
               {{ $attributes->merge(['class' => 'input']) }} @if($required) required @endif>
    @endif

    @if($help && $type !== 'checkbox')<p class="mt-1 text-xs text-ink-500">{{ $help }}</p>@endif

    @error($name)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
