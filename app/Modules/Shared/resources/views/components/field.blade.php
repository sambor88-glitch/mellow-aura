@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null, 'bag' => 'default', 'id' => null])
@php
    $id ??= $name;
    $error = $errors->getBag($bag)->first($name);
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'min-w-0']) }}>
    <label for="{{ $id }}" class="mb-1.5 block text-[13.5px] text-graphite">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value ?? old($name) }}"
           @if ($error || $hint) aria-describedby="{{ $id }}-note" @endif
           @if ($error) aria-invalid="true" @endif
           {{ $attributes->except('class') }}
           @class([
               'w-full min-w-0 rounded-[4px] border bg-white px-4 py-[15px] text-[15px] text-ink pointer-coarse:text-[16px] placeholder:text-hint focus:border-ink',
               'border-error' => $error,
               'border-line' => ! $error,
           ])>
    @if ($error)
        <p id="{{ $id }}-note" class="mt-1.5 text-[13px] text-error">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-note" class="mt-1.5 text-[12.5px] text-hint">{{ $hint }}</p>
    @endif
</div>
