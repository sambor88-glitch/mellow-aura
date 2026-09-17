@use('App\Modules\Content\Enums\Service')
@use('App\Modules\Shared\Support\Money')
@use('App\Modules\Shared\Support\ServiceStructuredData')
@use('Illuminate\Support\Facades\Vite')
@php
    $page = match ($service) {
        Service::Scarf => [
            'title' => 'Opaska z Twojej apaszki — szyję z Twojej tkaniny',
            'description' => 'Przyślij apaszkę babci albo sukienkę, z której wyrosłaś. Uszyję z niej opaskę i scrunchies — rzecz, która zostaje w rodzinie zamiast leżeć w szafie.',
            'eyebrow' => 'usługa &middot; z Twojej tkaniny',
            'photo' => 'zdjecia/opaska-jedwab.webp',
            'photoAlt' => 'Opaska i scrunchie uszyte z jedwabnej apaszki',
            'cta' => 'Napisz, co chcesz przysłać',
            'other' => [Service::Imprint, 'Odcisk Twojej rośliny →'],
            'examplesLead' => 'Tkaniny, które do mnie przyszły, obok tego, co z nich uszyłam. Przeciągnij uchwyt na zdjęciu, żeby zobaczyć zmianę.',
            'pricesHeading' => 'Co wychodzi z jednej apaszki',
        ],
        Service::Imprint => [
            'title' => 'Talerz z odciskiem Twojego kwiatu — pamiątka na lata',
            'description' => 'Zasuszony kwiat z bukietu ślubnego albo z pożegnania odciskam w glinie i wypalam. Roślina znika w piecu, rysunek zostaje na zawsze.',
            'eyebrow' => 'usługa &middot; z Twojej rośliny',
            'photo' => 'zdjecia/talerz-niebieski-odcisk.webp',
            'photoAlt' => 'Talerz z odciskiem prawdziwej rośliny',
            'cta' => 'Napisz, co chcesz odcisnąć',
            'other' => [Service::Scarf, 'Z Twojej apaszki →'],
            'examplesLead' => 'Rośliny, które do mnie przyszły, obok ceramiki, na której zostawiły ślad. Przeciągnij uchwyt na zdjęciu, żeby zobaczyć zmianę.',
            'pricesHeading' => 'Na czym odciskam',
        ],
    };
    $contactUrl = route('content.contact', ['temat' => 'Zamówienie indywidualne']);
    $label = 'pointer-events-none absolute top-2.5 rounded-full bg-cream/92 px-[11px] py-1 text-[10.5px] tracking-[0.14em] text-ink uppercase transition-opacity duration-250';
@endphp

<x-shared::layout :title="$page['title']" :description="$page['description']" :canonical="route($service->route())">
    {{-- For Google the service is named like the page title: „Opaska z Twojej apaszki”. --}}
    <x-slot:head>{!! ServiceStructuredData::for(Str::before($page['title'], ' — '), route($service->route()), $lead, $prices->map(fn (array $row) => [$row['label'], $row['price_gross']])) !!}</x-slot:head>
    <div class="animate-ma-view pb-24">
        <div class="mx-auto max-w-[1280px] px-7 pt-14">
            <div class="flex flex-wrap items-center gap-14">
                <div class="min-w-0 flex-[1_1_400px]">
                    <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">{!! $page['eyebrow'] !!}</div>
                    <h1 class="mb-6 font-serif text-[length:clamp(38px,5.4vw,72px)] leading-[1.02] font-light tracking-[-0.02em] whitespace-pre-line">{{ $heading ?: $service->label() }}</h1>
                    @if ($lead)
                        <p class="mb-5 max-w-[46ch] text-[17.5px] leading-[1.68] text-pretty text-graphite">{{ $lead }}</p>
                    @endif
                    @if ($lead2)
                        <p class="mb-[34px] max-w-[46ch] text-[16px] leading-[1.7] text-lead">{{ $lead2 }}</p>
                    @endif
                    <div class="flex flex-wrap gap-3.5">
                        <a href="{{ $contactUrl }}" class="rounded-full bg-ink px-8 py-4 text-[14px] text-linen transition duration-300 hover:bg-navy hover:text-linen active:scale-[.97]">{{ $page['cta'] }}</a>
                        <a href="{{ route($page['other'][0]->route()) }}" class="rounded-full border border-line-strong px-8 py-4 text-[14px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.98]">{{ $page['other'][1] }}</a>
                    </div>
                    @if ($phone)
                        <p class="mt-5 text-[14.5px] text-lead">Szybciej na WhatsAppie: <a href="https://wa.me/{{ preg_replace('/\D+/', '', $phone) }}" target="_blank" rel="noopener" class="whitespace-nowrap">{{ $phone }}</a></p>
                    @endif
                </div>
                <div class="min-w-0 flex-[1_1_320px]">
                    <img src="{{ Vite::asset($page['photo']) }}" alt="{{ $page['photoAlt'] }}" width="600" height="800" class="block aspect-[3/4] w-full rounded-[4px] bg-line-soft object-cover">
                </div>
            </div>
        </div>

        @if ($steps->isNotEmpty())
            <div class="mx-auto max-w-[1280px] px-7 pt-[72px]">
                <ol class="flex flex-wrap gap-px overflow-hidden rounded-[4px] border border-divider bg-divider">
                    @foreach ($steps as $step)
                        <li class="min-w-0 flex-[1_1_230px] bg-cream px-[26px] py-[30px]">
                            <div aria-hidden="true" class="mb-2 font-serif text-[20px] text-gold">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                            <h2 class="mb-1.5 text-[16px]">{{ $step['title'] }}</h2>
                            @if (filled($step['text'] ?? null))
                                <p class="text-[14.5px] leading-[1.6] text-muted">{{ $step['text'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
                <p class="mt-3.5 text-[13.5px] text-label">{{ $lockerCode ? 'Paczki do mnie: Paczkomat '.$lockerCode.'.' : 'Kod Paczkomatu, do którego wyślesz paczkę, podam w wiadomości.' }}</p>
            </div>
        @endif

        @if ($examples->isNotEmpty())
            <section aria-labelledby="przed-i-po" class="mx-auto max-w-[1280px] px-7 pt-[72px]">
                <h2 id="przed-i-po" class="mb-2 font-serif text-[length:clamp(28px,3.4vw,40px)] leading-[1.1] font-light text-pretty">Przed i po</h2>
                <p class="mb-[26px] max-w-[52ch] text-[15.5px] leading-[1.6] text-muted">{{ $page['examplesLead'] }}</p>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,340px),1fr))] gap-[26px]">
                    @foreach ($examples as $example)
                        <figure x-data="beforeAfter" class="m-0 self-start overflow-hidden rounded-[4px] border border-divider bg-cream">
                            <div x-on:pointerdown="start($event)" x-on:pointermove="drag($event)" x-on:pointerup="stop()" x-on:pointercancel="stop()"
                                 class="relative aspect-[4/5] cursor-ew-resize touch-none overflow-hidden bg-line-soft select-none">
                                <img src="{{ $example->getFirstMedia('after')->getAvailableUrl(['card']) }}" alt="{{ $example->alt('after') }}" loading="lazy" draggable="false" width="960" height="1200" class="absolute inset-0 block size-full object-cover">
                                <img src="{{ $example->getFirstMedia('before')->getAvailableUrl(['card']) }}" alt="{{ $example->alt('before') }}" loading="lazy" draggable="false" width="960" height="1200"
                                     class="absolute inset-0 block size-full object-cover" style="clip-path: inset(0 50% 0 0)" x-bind:style="{ clipPath: `inset(0 ${100 - pos}% 0 0)` }">
                                <span class="{{ $label }} left-2.5" x-bind:class="pos < 12 && 'opacity-0'">przed</span>
                                <span class="{{ $label }} right-2.5" x-bind:class="pos > 88 && 'opacity-0'">po</span>
                                <div aria-hidden="true" class="pointer-events-none absolute inset-y-0 w-0.5 bg-cream shadow-[0_0_0_1px_rgba(47,38,32,.18)]" style="left: 50%" x-bind:style="{ left: pos + '%' }"></div>
                                <div role="slider" tabindex="0" aria-label="Przeciągnij, żeby porównać przed i po" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50"
                                     x-bind:aria-valuenow="pos" x-on:keydown="key($event)"
                                     class="absolute top-1/2 -mt-[22px] -ml-[22px] grid size-11 cursor-ew-resize grid-flow-col place-items-center gap-0.5 rounded-full bg-cream text-[13px] text-ink shadow-[0_0_0_1px_rgba(47,38,32,.18)] focus-visible:outline-offset-0"
                                     style="left: 50%" x-bind:style="{ left: pos + '%' }"><span aria-hidden="true">‹</span><span aria-hidden="true">›</span></div>
                            </div>
                            @if ($example->caption)
                                <figcaption class="px-[18px] py-3.5 text-[14px] leading-[1.5] text-lead">{{ $example->caption }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="mx-auto max-w-[1280px] px-7 pt-14">
            <div class="flex flex-wrap gap-11">
                <div class="min-w-0 flex-[1_1_400px]">
                    @if ($prices->isNotEmpty())
                        <h2 class="mb-[22px] font-serif text-[length:clamp(28px,3.4vw,40px)] leading-[1.1] font-light text-pretty">{{ $page['pricesHeading'] }}</h2>
                        <div class="overflow-hidden rounded-[4px] border border-divider bg-cream">
                            @foreach ($prices as $price)
                                <div class="flex justify-between gap-5 border-b border-sand-dark px-6 py-5 last:border-b-0">
                                    <div class="min-w-0">
                                        <div class="text-[16px]">{{ $price['label'] }}</div>
                                        @if (filled($price['note'] ?? null))
                                            <div class="mt-[3px] text-[13.5px] text-label">{{ $price['note'] }}</div>
                                        @endif
                                    </div>
                                    <div class="flex-none text-right text-[16px] whitespace-nowrap tabular-nums">
                                        {{ Money::format($price['price_gross']) }}
                                        <x-shared::price-before-reduction :was="$price['was']" :lowest="$price['lowest']" class="mt-1 ml-auto max-w-[20ch] whitespace-normal" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if ($note)
                        <p class="mt-3.5 text-[13px] leading-[1.6] text-hint">{{ $note }}</p>
                    @endif
                </div>
                <div class="min-w-[280px] flex-[1_1_300px]">
                    @include('content::services.'.$service->value.'-card')
                </div>
            </div>
        </div>
    </div>
</x-shared::layout>
