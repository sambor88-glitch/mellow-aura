{{-- describedby: the id of a note outside the field that also describes it, e.g. one hint under two fields side by side. --}}
@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null, 'bag' => 'default', 'id' => null, 'optional' => false, 'describedby' => null])
@php
    $id ??= $name;
    $error = $errors->getBag($bag)->first($name);
    $notes = trim(($error || $hint ? $id.'-note ' : '').$describedby);
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'min-w-0']) }}>
    <label for="{{ $id }}" class="mb-1.5 block text-[13.5px] text-graphite">{{ $label }}@if ($optional) <span class="text-hint">{{ __('shared::forms.optional') }}</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value ?? old($name) }}"
           @if ($notes !== '') aria-describedby="{{ $notes }}" @endif
           @if ($error) aria-invalid="true" @endif
           {{ $attributes->except('class') }}
           @class([
               'w-full min-w-0 rounded-[14px] border bg-white px-4 py-[15px] text-[15px] text-ink transition-[border-color,box-shadow] duration-300 pointer-coarse:text-[16px] placeholder:text-hint focus:border-ink focus:shadow-[0_0_0_4px_rgb(36_65_126/.14)]',
               'border-error' => $error,
               'border-line' => ! $error,
           ])>
    @if ($error)
        <p id="{{ $id }}-note" class="mt-1.5 text-[13px] text-error">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-note" class="mt-1.5 text-[12.5px] text-hint">{{ $hint }}</p>
    @endif
</div>
