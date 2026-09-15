@props(['name', 'label', 'type' => 'text', 'hint' => null])
@php($error = $errors->first($name))
<div {{ $attributes->only('class')->merge(['class' => 'min-w-0']) }}>
    <label for="{{ $name }}" class="mb-1.5 block text-[13.5px] text-graphite">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}"
           @if ($error || $hint) aria-describedby="{{ $name }}-note" @endif
           @if ($error) aria-invalid="true" @endif
           {{ $attributes->except('class') }}
           @class([
               'w-full min-w-0 rounded-[4px] border bg-white px-4 py-[15px] text-[15px] text-ink placeholder:text-hint focus:border-ink',
               'border-error' => $error,
               'border-line' => ! $error,
           ])>
    @if ($error)
        <p id="{{ $name }}-note" class="mt-1.5 text-[13px] text-error">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $name }}-note" class="mt-1.5 text-[12.5px] text-hint">{{ $hint }}</p>
    @endif
</div>
