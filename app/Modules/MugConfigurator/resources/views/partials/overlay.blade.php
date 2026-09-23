{{-- The glaze's tint and the text on the photo, placed where the panel says. Both previews of the configurator draw it. --}}
<div aria-hidden="true" class="pointer-events-none absolute inset-0 opacity-[.07] mix-blend-multiply transition-[background-color] duration-600"
     style="background-color: {{ $startGlaze['hex'] }}" x-bind:style="{ backgroundColor: glazeHex }"></div>
{{-- The text is only a picture of what the field holds, so screen readers skip it. --}}
<div aria-hidden="true" class="absolute w-[60%] text-center text-[length:clamp(15px,2.3vw,26px)]"
     style="left: {{ $position['x'] }}%; top: {{ $position['y'] }}%; transform: translate(-50%, -50%) rotate({{ $position['rotation'] }}deg)">
    <span x-show="false" class="block leading-[1.35] tracking-[0.16em]" style="font-size: {{ $position['size'] }}%; color: {{ $ink['hex'] }}">{{ __('mug-configurator::mug.js.sample') }}</span>
    <template x-for="line in preview" x-bind:key="line.key">
        <span class="block leading-[1.35] tracking-[0.16em] [overflow-wrap:anywhere]"
              style="font-size: {{ $position['size'] }}%; color: {{ $ink['hex'] }}; text-shadow: 0 1px 0 rgba(255, 255, 255, .35)">
            <template x-for="item in line.letters" x-bind:key="item.key">
                <span class="inline-block animate-ma-stamp whitespace-pre" x-text="item.letter"></span>
            </template>
        </span>
    </template>
</div>
