@props(['name', 'label', 'limit', 'max', 'value' => null, 'id' => null, 'bag' => 'default', 'errorKey' => null, 'placeholder' => null, 'rows' => null])
{{-- A title or description for Google with a live character count. Past $limit Google cuts the text, so the
     counter turns red; the form still saves up to $max. An empty field means „compose it automatically” and the
     placeholder shows what the page will compose. --}}
@php
    $id ??= $name;
    $error = $errors->getBag($bag)->first($errorKey ?? $name);
    $length = mb_strlen((string) $value);
    $classes = 'w-full min-w-0 rounded-[4px] border bg-white px-4 py-[13px] text-[15px] text-ink placeholder:text-hint focus:border-ink';
@endphp
<div {{ $attributes->merge(['class' => 'min-w-0']) }} x-data="{ length: {{ $length }} }">
    <label for="{{ $id }}" class="mb-1.5 block text-[13.5px] text-graphite">{{ $label }}</label>
    @if ($rows)
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" maxlength="{{ $max }}" placeholder="{{ $placeholder }}"
                  aria-describedby="{{ $id }}-count" @if ($error) aria-invalid="true" @endif
                  x-on:input="length = [...$el.value].length"
                  @class([$classes, 'resize-y leading-[1.6]', 'border-error' => $error, 'border-line' => ! $error])>{{ $value }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" maxlength="{{ $max }}" placeholder="{{ $placeholder }}"
               aria-describedby="{{ $id }}-count" @if ($error) aria-invalid="true" @endif
               x-on:input="length = [...$el.value].length"
               @class([$classes, 'border-error' => $error, 'border-line' => ! $error])>
    @endif
    <p id="{{ $id }}-count" data-seo-count class="mt-1.5 flex flex-wrap justify-between gap-x-3 text-[12.5px]"
       :class="length > {{ $limit }} ? 'text-error' : 'text-hint'" @class(['text-error' => $length > $limit, 'text-hint' => $length <= $limit])>
        <span x-text="length > {{ $limit }} ? 'Google utnie po {{ $limit }} znakach' : (length ? 'Mieści się w Google' : 'Puste pole — strona złoży tekst sama')">{{ match (true) {
            $length > $limit => 'Google utnie po '.$limit.' znakach',
            $length > 0 => 'Mieści się w Google',
            default => 'Puste pole — strona złoży tekst sama',
        } }}</span>
        <span><span x-text="length">{{ $length }}</span> / {{ $limit }}</span>
    </p>
    @if ($error)
        <p class="mt-1 text-[13px] text-error">{{ $error }}</p>
    @endif
</div>
