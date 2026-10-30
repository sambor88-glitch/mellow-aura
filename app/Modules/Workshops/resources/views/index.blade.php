@use('App\Modules\Shared\Support\Money')
@use('App\Modules\Workshops\Support\WorkshopTypes')
@php
    // The topic name matches the contact form's list from the panel; if it is renamed, the form simply opens without it.
    $contactUrl = route('content.contact', ['temat' => 'Warsztaty i terminy']);
    $vouchersUrl = Route::has('vouchers.index') ? route('vouchers.index') : null;
    $chip = 'rounded-full bg-sand-dark px-[11px] py-[5px] text-[11.5px] tracking-[0.1em] text-muted uppercase';
    // The filter only helps when there is something to choose between.
    $filtered = $workshops->filter(fn (array $workshop) => $dateCounts->has(WorkshopTypes::key($workshop)));
    $pills = collect([['code' => null, 'label' => 'Wszystkie']])
        ->concat($filtered->map(fn (array $workshop) => ['code' => WorkshopTypes::key($workshop), 'label' => $workshop['name']]));
    // The photo spans the 1280 px page without its padding.
    $heroSizes = '(min-width: 1280px) 1224px, calc(100vw - 56px)';
@endphp

<x-shared::layout title="Warsztaty ceramiczne Kraków — kameralnie, do 6 osób"
                  description="Warsztaty ceramiczne w Krakowie: lepienie kubków, szkliwienie, sesje 1:1, dla par i rodzin. Glina, narzędzia i dwa wypały w cenie. Terminy i cennik."
                  :canonical="route('workshops.index')"
                  :preload-image="$hero !== null ? ['src' => $hero, 'srcset' => $heroSrcset, 'sizes' => $heroSizes] : null">
    <x-slot:head>{!! $structuredData !!}</x-slot:head>
    {{-- „Terminy (n)” on a card shows that workshop's dates only; without JS the list shows them all. --}}
    <div x-data="{
            type: null,
            show(code) {
                this.type = code;
                // In the next frame: the list has changed by then, and Lenis has heard of any scroll the tap itself made
                // (focus, a test's click) — before it, that scroll cancelled this one. scroll-mt keeps the heading clear of the header.
                requestAnimationFrame(() => {
                    // On a phone the chosen pill may sit past the edge of its row.
                    const pill = this.$refs.pills?.querySelector('[aria-pressed=true]');
                    if (pill) this.$refs.pills.scrollLeft = pill.offsetLeft - 28;
                    const list = document.getElementById('workshop-dates');
                    window.auraLenis ? window.auraLenis.scrollTo(list) : list.scrollIntoView({ block: 'start' });
                });
            },
         }"
         class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">warsztaty &middot; cennik</div>
        <h1 class="mb-[18px] font-serif text-[length:clamp(38px,5.2vw,70px)] leading-[1.04] font-light tracking-[-0.02em]">Zanurz dłonie<br>w glinie</h1>
        @if ($lead)
            <p class="mb-[52px] max-w-[56ch] text-[17px] leading-[1.7] text-pretty text-lead">{{ $lead }}</p>
        @endif

        @if ($hero !== null)
            <img src="{{ $hero }}" @if ($heroSrcset) srcset="{{ $heroSrcset }}" sizes="{{ $heroSizes }}" @endif alt="" fetchpriority="high" width="1280" height="520"
                 class="mb-[76px] block h-[clamp(240px,40vh,460px)] w-full rounded-[20px] bg-line-soft object-cover">
        @endif

        @if ($workshops->isNotEmpty())
            <div class="mb-[76px] grid grid-cols-[repeat(auto-fit,minmax(min(100%,290px),1fr))] gap-[22px]">
                @foreach ($workshops as $workshop)
                    @php
                        $code = WorkshopTypes::key($workshop);
                        $count = $dateCounts->get($code, 0);
                    @endphp
                    <article id="workshop-{{ $code }}" class="flex animate-ma-up scroll-mt-24 flex-col rounded-[20px] border border-divider bg-cream px-7 py-[30px]" style="animation-delay: {{ $loop->index * 0.06 }}s">
                        {{-- The price under the name: beside it, it squeezed the name into two or three lines in a narrow card. --}}
                        <div class="mb-3.5 flex flex-col gap-2">
                            <h2 class="min-w-0 font-serif text-[26px] leading-[1.15] text-balance">{{ $workshop['name'] }}</h2>
                            <div class="flex items-baseline gap-1.5">
                                <div class="font-serif text-[25px] leading-none tabular-nums">{{ Money::format($workshop['price_gross']) }}</div>
                                @if ($workshop['unit_label'])
                                    <div class="text-[12.5px] text-label">/ {{ $workshop['unit_label'] }}</div>
                                @endif
                            </div>
                        </div>
                        <x-shared::price-before-reduction :was="$workshop['was']" :lowest="$workshop['lowest']" class="-mt-1.5 mb-3.5" />
                        @if ($workshop['duration_label'] || $workshop['group_label'])
                            <div class="mb-4 flex flex-wrap gap-2">
                                @foreach (array_filter([$workshop['duration_label'], $workshop['group_label']]) as $fact)
                                    <span class="{{ $chip }}">{{ $fact }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if ($workshop['summary'])
                            <p class="mb-[18px] text-[15px] leading-[1.62] text-lead">{{ $workshop['summary'] }}</p>
                        @endif
                        @if ($workshop['includes'])
                            <ul class="mt-auto grid gap-2 border-t border-sand-dark pt-4">
                                @foreach ($workshop['includes'] as $included)
                                    <li class="flex gap-2.5 text-[13.5px] text-muted"><span aria-hidden="true" class="text-gold">✦</span><span>{{ $included }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($count > 0)
                            <a href="#workshop-dates" x-on:click.prevent="show(@js($code))"
                               @class(['inline-flex min-h-11 items-center justify-center gap-1.5 rounded-full bg-ink px-6 py-3 text-[14px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]', 'mt-5' => $workshop['includes'], 'mt-auto' => ! $workshop['includes']])>Terminy ({{ $count }}) <span aria-hidden="true">↓</span></a>
                        @else
                            <a href="{{ $contactUrl }}"
                               @class(['inline-flex min-h-11 items-center justify-center rounded-full border border-ink px-6 py-3 text-[14px] text-ink transition duration-300 hover:bg-ink hover:text-linen active:scale-[.97]', 'mt-5' => $workshop['includes'], 'mt-auto' => ! $workshop['includes']])>Zapytaj o termin</a>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap gap-11">
            <div class="min-w-0 flex-[1_1_420px]">
                @if ($dates->isNotEmpty())
                    <section aria-labelledby="workshop-dates" class="mb-14">
                        <h2 id="workshop-dates" class="mb-2 scroll-mt-24 font-serif text-[length:clamp(28px,3.4vw,40px)] font-light">Najbliższe terminy</h2>
                        <p class="mb-[26px] text-[15.5px] text-muted">Wybierz termin i zapłać BLIK-iem, kartą albo przez Przelewy24 — miejsce jest Twoje od razu.</p>
                        @if ($filtered->count() > 1)
                            {{-- Buttons only work with JS, so without it the row is not there at all. On a phone one row swiped sideways. --}}
                            <div x-cloak x-ref="pills" role="group" aria-label="Pokaż terminy warsztatu"
                                 class="relative -mx-7 mb-5 flex gap-2 overflow-x-auto px-7 pb-1 [scrollbar-width:none] md:mx-0 md:flex-wrap md:overflow-visible md:px-0 md:pb-0 [&::-webkit-scrollbar]:hidden">
                                @foreach ($pills as $pill)
                                    <button type="button" x-on:click="type = @js($pill['code'])" x-bind:aria-pressed="type === @js($pill['code'])"
                                            class="min-h-11 shrink-0 rounded-full border border-line-strong px-[17px] text-[13px] whitespace-nowrap text-lead transition-colors duration-300 hover:border-ink hover:text-ink aria-pressed:border-ink aria-pressed:bg-ink aria-pressed:text-linen">{{ $pill['label'] }}</button>
                                @endforeach
                            </div>
                        @endif
                        <ul class="grid gap-3">
                            @foreach ($dates as ['date' => $date, 'workshop' => $workshop])
                                @php
                                    $when = $date->dayLabel().', '.$date->timeLabel();
                                @endphp
                                <li x-show="type === null || type === @js($date->workshop_code)" class="flex flex-wrap items-center gap-4 rounded-[20px] border border-divider bg-cream px-[22px] py-5">
                                    <div class="min-w-0 flex-[1_1_200px]">
                                        <div class="font-serif text-[19px] leading-[1.2]">
                                            <time datetime="{{ $date->starts_at->toIso8601String() }}">{{ $date->dayLabel() }}</time>
                                        </div>
                                        <div class="mt-[3px] text-[13.5px] text-muted">{{ $date->timeLabel() }} &middot; {{ $workshop['name'] }}</div>
                                    </div>
                                    {{-- Fixed widths keep „Wybieram” in one column; on a phone seats and price left, the button right, under a line. --}}
                                    <div class="flex items-center gap-5 max-sm:w-full max-sm:justify-between max-sm:border-t max-sm:border-divider max-sm:pt-4 sm:flex-none">
                                        <div class="sm:w-[190px] sm:text-right">
                                            <div @class(['text-[12.5px]', 'text-error' => $date->seatsLeft() <= 1, 'text-success-text' => $date->seatsLeft() > 1])>{{ $date->seatsLeftLabel() }}</div>
                                            <div class="mt-0.5 text-[14px] tabular-nums">{{ Money::format($workshop['price_gross']) }}@if ($workshop['unit_label']) / {{ $workshop['unit_label'] }}@endif</div>
                                        </div>
                                        @if ($date->seatsLeft() > 0)
                                            <a href="{{ route('workshops.booking.create', $date) }}" aria-label="Wybieram: {{ $workshop['name'] }}, {{ $when }}"
                                               class="inline-flex min-h-11 w-[118px] flex-none items-center justify-center rounded-full border border-ink text-[13px] text-ink transition duration-300 hover:bg-ink hover:text-linen active:scale-[.97]">Wybieram</a>
                                        @else
                                            <span aria-hidden="true" class="w-[118px] flex-none max-sm:hidden"></span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <h2 class="mb-2 font-serif text-[length:clamp(28px,3.4vw,40px)] font-light">Jak się zapisać</h2>
                <p class="mb-6 max-w-[52ch] text-[15.5px] leading-[1.65] text-muted">
                    @if ($dates->isNotEmpty())
                        Wybierz termin powyżej i zapłać przy zapisie. Nie widzisz pasującego albo przychodzi większa grupa? Napisz, który warsztat Cię ciekawi i ile osób przyjdzie.
                    @else
                        Napisz, który warsztat Cię ciekawi i ile osób przyjdzie — ustalimy termin.
                    @endif
                </p>
                @if ($location)
                    <p class="mb-6 max-w-[52ch] text-[15px] leading-[1.65] text-lead">Pracownia: {{ $location }}. Dokładny adres podaję po zapisie.</p>
                @endif
                @if ($vouchersUrl)
                    <a href="{{ $vouchersUrl }}" class="border-b border-line-strong pb-[3px] text-[13.5px] tracking-[0.06em]">Warsztat na prezent? Zobacz vouchery →</a>
                @endif
            </div>
            <div class="min-w-[min(100%,280px)] flex-[0_1_340px]">
                <div class="focus-on-dark rounded-[20px] bg-ink px-7 py-[30px] text-divider">
                    <div class="mb-[18px] text-[11px] tracking-[0.2em] text-gold uppercase">zapisy</div>
                    <p class="mb-5 font-serif text-[21px] leading-[1.4] text-cream">
                        {{-- With dates on the page they are booked online; the box is for what the calendar does not cover (MA-294). --}}
                        @if ($dates->isNotEmpty())
                            Żaden termin nie pasuje albo przychodzi grupa? Napisz, który warsztat i ile osób — znajdę inny dzień.
                        @else
                            Napisz, który warsztat i ile osób — odpiszę z wolnymi terminami.
                        @endif
                    </p>
                    <div class="grid gap-3">
                        @if ($phone)
                            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $phone) }}" target="_blank" rel="noopener"
                               class="rounded-full bg-rose p-[15px] text-center text-[14.5px] text-ink transition duration-300 hover:bg-sand hover:text-ink active:scale-[.97]">Napisz na WhatsAppie</a>
                        @endif
                        <a href="{{ $contactUrl }}"
                           class="rounded-full border border-line-dark p-[15px] text-center text-[14.5px] text-sand transition duration-300 hover:border-rose hover:text-rose active:scale-[.98]">Napisz przez formularz</a>
                    </div>
                </div>
            </div>
        </div>

        @if ($gallery->isNotEmpty())
            <div class="pt-[76px]">
                <div class="mb-5 flex flex-wrap items-baseline justify-between gap-4">
                    <h2 class="font-serif text-[length:clamp(28px,3.4vw,40px)] font-light">Pracownia</h2>
                    <a href="{{ route('content.studio') }}" class="border-b border-line-strong pb-[3px] text-[13.5px] tracking-[0.06em]">Zobacz pracownię →</a>
                </div>
                <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3.5">
                    @foreach ($gallery as $photo)
                        <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" loading="lazy" width="600" height="600" class="block aspect-square w-full rounded-[20px] bg-line-soft object-cover">
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-shared::layout>
