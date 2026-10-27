{{--
    Sliders that move the point of a photo kept in view when a frame cuts it (Shared\Support\FocalPoint), with a live
    preview in the shapes the photo takes on the site. Sends {name}[x] and {name}[y], each 0–100.

    frames: aspect class => what the shape is, e.g. ['aspect-[4/5]' => 'w sklepie', 'aspect-square' => 'na karcie'].
    step: 1 where a saved point is not a multiple of 5, so the slider does not round it off.
--}}
@props(['name', 'src', 'x' => 50, 'y' => 50, 'label', 'frames' => ['aspect-square' => null], 'step' => 5])
@php
    $x = (int) $x;
    $y = (int) $y;
@endphp
<fieldset x-data="{ x: {{ $x }}, y: {{ $y }} }" {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <legend class="sr-only">{{ $label }}</legend>
    <div class="flex items-end gap-2">
        @foreach ($frames as $aspect => $caption)
            <figure class="m-0 min-w-0 flex-1">
                <img src="{{ $src }}" alt="" loading="lazy" style="object-position: {{ $x }}% {{ $y }}%" x-bind:style="`object-position: ${x}% ${y}%`"
                     class="block {{ $aspect }} w-full rounded-[3px] bg-line-soft object-cover">
                @if ($caption)
                    <figcaption class="mt-1 text-center text-[11px] text-label">{{ $caption }}</figcaption>
                @endif
            </figure>
        @endforeach
    </div>
    @foreach (['y' => 'Góra — dół', 'x' => 'Lewo — prawo'] as $axis => $text)
        <label class="mt-1.5 block">
            <span class="block text-[12px] text-muted">{{ $text }}</span>
            <input type="range" name="{{ $name }}[{{ $axis }}]" min="0" max="100" step="{{ $step }}" value="{{ $$axis }}" x-model.number="{{ $axis }}" class="h-11 w-full accent-ink">
        </label>
    @endforeach
</fieldset>
