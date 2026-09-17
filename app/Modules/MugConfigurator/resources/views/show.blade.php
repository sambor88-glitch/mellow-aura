@use('App\Modules\Shared\Support\Money')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $sizes = $options->sizes();
    $glazes = $options->glazes();
    $ink = $options->ink();
    $position = $options->position();
    $photo = $options->photoUrl();
    $maxLines = $options->maxLines();
    $maxChars = $options->maxCharsPerLine();
    $startGlaze = $glazes->first();

    $heading = $settings->get('text_mug_heading', 'Kubek z napisem');
    $lead = $settings->get('text_mug_lead');
    $note = $settings->get('text_mug_note');
    $leadTime = $settings->get('text_mug_lead_time');
    $bulkOrder = $settings->get('text_mug_bulk_order');
    $refusals = $settings->get('text_mug_refusals');
    $freeShipping = $settings->get('free_shipping_threshold');

    $eyebrow = 'text-[11.5px] tracking-[0.16em] text-label uppercase';
    $pill = 'min-h-11 rounded-full border border-line px-[13px] py-1.5 text-[12px] text-lead transition duration-300 hover:border-ink active:scale-[.98]';
@endphp

<x-shared::layout
    title="Kubek z napisem na zamówienie — Twój tekst wbity w glinę"
    description="Zaprojektuj kubek z własnym napisem: litery wbijane stemplem w glinę, trzy rozmiary, pięć kolorów szkliwa. Prezent, którego nie ma nikt inny. Kraków."
    :canonical="route('mug.index')"
    :image="$photo"
>
    <x-slot:head>{!! $structuredData !!}</x-slot:head>
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-11 pb-24"
         x-data="mugConfigurator({
             maxLines: {{ $maxLines }},
             maxChars: {{ $maxChars }},
             prices: @js($sizes->pluck('price_gross', 'label')),
             glazes: @js($glazes->keyBy('code')),
             size: @js($startSize['label']),
             glaze: @js($startGlaze['code']),
         })">
        <nav aria-label="Okruszki" class="mb-[26px] text-[12px] text-hint">
            <a href="{{ url('/') }}" class="text-hint hover:text-navy">Strona główna</a> / <span class="text-lead">Kubek z napisem</span>
        </nav>

        <div class="flex flex-wrap gap-14">
            <div class="min-w-0 flex-[1_1_380px]">
                <div class="relative overflow-hidden rounded-[6px] bg-line-soft">
                    @if ($photo)
                        <img src="{{ $photo }}" alt="Kubek z wbijanym w glinę napisem" fetchpriority="high" class="block aspect-square w-full object-cover">
                    @else
                        <div class="aspect-square w-full"></div>
                    @endif
                    <div aria-hidden="true" class="pointer-events-none absolute inset-0 opacity-[.07] mix-blend-multiply transition-[background-color] duration-600"
                         style="background-color: {{ $startGlaze['hex'] }}" x-bind:style="{ backgroundColor: glazeHex }"></div>
                    {{-- The text is only a picture of what the field holds, so screen readers skip it. --}}
                    <div aria-hidden="true" class="absolute w-[60%] text-center text-[length:clamp(15px,2.3vw,26px)]"
                         style="left: {{ $position['x'] }}%; top: {{ $position['y'] }}%; transform: translate(-50%, -50%) rotate({{ $position['rotation'] }}deg)">
                        <span x-show="false" class="block leading-[1.35] tracking-[0.16em]" style="font-size: {{ $position['size'] }}%; color: {{ $ink['hex'] }}">TWÓJ NAPIS</span>
                        <template x-for="line in preview" x-bind:key="line.key">
                            <span class="block leading-[1.35] tracking-[0.16em] [overflow-wrap:anywhere]"
                                  style="font-size: {{ $position['size'] }}%; color: {{ $ink['hex'] }}; text-shadow: 0 1px 0 rgba(255, 255, 255, .35)">
                                <template x-for="item in line.letters" x-bind:key="item.key">
                                    <span class="inline-block animate-ma-stamp whitespace-pre" x-text="item.letter"></span>
                                </template>
                            </span>
                        </template>
                    </div>
                    <div class="absolute right-4 bottom-4 flex items-center gap-[9px] rounded-full bg-cream/92 px-[13px] py-[7px]">
                        <span aria-hidden="true" class="size-[15px] rounded-full transition-[background-color] duration-600"
                              style="background-color: {{ $startGlaze['hex'] }}" x-bind:style="{ backgroundColor: glazeHex }"></span>
                        <span class="text-[12px] text-lead">wnętrze: <span x-text="glazeName">{{ $startGlaze['name'] }}</span></span>
                    </div>
                </div>
                @if ($note)
                    <p class="mt-3 text-[12.5px] leading-[1.6] text-hint">{{ $note }}</p>
                @endif
            </div>

            <div class="min-w-0 flex-[1_1_360px]">
                <div class="mb-4 text-[10.5px] tracking-[0.28em] text-brown uppercase">na zamówienie @if ($leadTime) &middot; {{ $leadTime }} @endif</div>
                <h1 class="mb-[18px] font-serif text-[length:clamp(34px,4.6vw,56px)] leading-[1.04] font-light whitespace-pre-line">{{ $heading }}</h1>
                @if ($lead)
                    <p class="mb-8 max-w-[46ch] text-[16.5px] leading-[1.7] text-pretty text-lead">{{ $lead }}</p>
                @endif

                <form method="post" action="{{ route('cart.store') }}" x-on:submit.prevent="add($el)">
                    @csrf
                    <input type="hidden" name="type" value="mug">

                    <label for="mug-text" class="mb-3 block {{ $eyebrow }}">Twój napis</label>
                    <textarea id="mug-text" name="text" x-ref="text" x-on:input="typed($event)" rows="{{ min(3, $maxLines) }}" autocomplete="off" spellcheck="false"
                              placeholder="NIE POWINNAM&#10;ALE JEDNAK" aria-describedby="mug-text-rules"
                              class="block w-full min-w-0 resize-none rounded-[4px] border border-line bg-cream px-[18px] py-[17px] text-[19px] leading-[1.5] tracking-[0.14em] text-ink uppercase placeholder:text-hint focus:border-ink"></textarea>
                    <div class="mt-3 mb-[30px] flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-label">
                        <span id="mug-text-rules">
                            <span x-text="lineInfo">Enter przenosi wyraz do nowej linii</span><span class="sr-only">. Najwyżej {{ $maxLines }} × {{ $maxChars }} znaków.</span>
                        </span>
                        <span class="flex flex-wrap items-center gap-3.5">
                            <button type="button" x-on:click="toggleSound()" class="{{ $pill }}"
                                    x-text="sound ? 'Wycisz stempel' : 'Włącz dźwięk stempla'">Wycisz stempel</button>
                            <button type="button" x-on:click="split()" class="{{ $pill }}">Podziel na linie</button>
                            <span aria-hidden="true" title="do {{ $maxLines }} linii po {{ $maxChars }} znaków" x-text="counter">0/{{ $maxChars }}</span>
                        </span>
                    </div>

                    <fieldset class="mb-[30px]">
                        <legend class="mb-3 {{ $eyebrow }}">Rozmiar</legend>
                        <div class="flex flex-wrap gap-2.5">
                            @foreach ($sizes as $size)
                                <label class="group flex cursor-pointer flex-col rounded-[6px] border border-line bg-cream px-[18px] py-3 text-left text-lead has-checked:border-ink has-checked:bg-ink has-checked:text-linen has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                    <input type="radio" name="size" value="{{ $size['label'] }}" x-model="size" @checked($size['label'] === $startSize['label']) class="sr-only">
                                    <span class="text-[13.5px]">{{ $size['name'] }}</span>
                                    <span class="mt-[3px] text-[12.5px] text-label group-has-checked:text-line-strong">{{ Money::format($size['price_gross']) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="mb-[34px]">
                        <legend class="mb-3 {{ $eyebrow }}">Kolor wnętrza</legend>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($glazes as $glaze)
                                <label title="{{ $glaze['name'] }}" style="background-color: {{ $glaze['hex'] }}"
                                       class="relative size-[42px] cursor-pointer rounded-full border-2 border-transparent shadow-[inset_0_0_0_2px_var(--color-cream)] transition-[border-color,transform] duration-300 ease-clay after:absolute after:-inset-1 hover:scale-112 has-checked:border-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                    <input type="radio" name="glaze" value="{{ $glaze['code'] }}" x-model="glaze" @checked($glaze['code'] === $startGlaze['code']) class="sr-only">
                                    <span class="sr-only">{{ $glaze['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <button class="w-full rounded-full bg-ink px-[30px] py-[18px] text-[15px] tracking-[0.02em] text-linen transition duration-300 hover:bg-navy active:scale-[.97]">
                        Dodaj do koszyka &middot; <span x-text="price">{{ Money::format($startSize['price_gross']) }}</span>
                    </button>
                </form>

                <div class="mt-4 flex items-center gap-2.5 text-[12.5px] text-muted">
                    <span class="rounded-[3px] bg-navy px-[7px] py-[3px] text-[10px] font-semibold text-white">BLIK</span>
                    <span>zapłacisz w 10 sekund{{ $freeShipping ? ' · wysyłka gratis od '.Money::format((int) $freeShipping) : '' }}</span>
                </div>

                @if ($bulkOrder || $refusals)
                    <div class="mt-8 grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-5 border-t border-divider pt-[26px] text-[13.5px] leading-[1.55] text-muted">
                        @if ($bulkOrder)
                            <div>
                                <div class="mb-1 text-ink">Zamawiasz więcej?</div>
                                {{ $bulkOrder }}
                            </div>
                        @endif
                        @if ($refusals)
                            <div>
                                <div class="mb-1 text-ink">Czego nie wbiję</div>
                                {{ $refusals }}
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-shared::layout>
