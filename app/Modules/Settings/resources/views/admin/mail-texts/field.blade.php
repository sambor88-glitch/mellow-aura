@php
    $field = $fields[$key];
    $invalid = $bag->has($key);
@endphp
<div class="min-w-0">
    <label for="maile-{{ $key }}" class="{{ $label }}">{{ $parts[$field['part']] }}</label>
    @if ($field['lines'] > 1)
        <textarea id="maile-{{ $key }}" name="{{ $key }}" rows="{{ $field['lines'] }}" maxlength="{{ $field['max'] }}" placeholder="{{ $defaults[$key] }}"
                  @class([$input, 'resize-y leading-[1.6]', 'border-error' => $invalid, 'border-line' => ! $invalid])>{{ old($key, $values[$key]) }}</textarea>
    @else
        <input id="maile-{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}" maxlength="{{ $field['max'] }}" placeholder="{{ $defaults[$key] }}"
               @class([$input, 'border-error' => $invalid, 'border-line' => ! $invalid])>
    @endif
    @if ($invalid)
        <p class="mt-1.5 text-[13px] text-error">{{ $bag->first($key) }}</p>
    @else
        <p class="mt-1.5 text-[12.5px] text-label">Do {{ $field['max'] }} znaków{{ $field['lines'] > 1 ? ', najwyżej '.$field['lines'].' linijki' : '' }}.</p>
    @endif
</div>
