@use('App\Modules\Shared\Support\DispatchTime')
@use('App\Modules\Shared\Support\Money')
@use('Illuminate\Support\Facades\Vite')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    // A section or button that leads to another page shows up once that page has a route.
    $link = fn (string $route) => Route::has($route) ? route($route) : null;

    $heroImage = $hero?->getFirstMedia('images');
    $heroBadge = $settings->get('home_hero_badge');
    $kasiaHeading = $settings->get('text_home_kasia_heading');
    $kasiaParagraph = $settings->get('text_home_kasia_paragraph');

    $lowestPrice = fn (mixed $rows) => collect(is_array($rows) ? $rows : [])->pluck('price_gross')->filter()->min();
    $services = array_filter([
        $link('content.scarf') ? [$link('content.scarf'), 'opaska-jedwab.webp', 'Opaska z jedwabnej apaszki', 'Z Twojej apaszki', 'Apaszka po babci wraca jako opaska i dwie scrunchies. Resztki oddaję.', $lowestPrice($settings->get('scarf_service_prices'))] : null,
        $link('content.imprint') ? [$link('content.imprint'), 'talerz-niebieski-odcisk.webp', 'Talerz z odciskiem rośliny', 'Odcisk Twojej rośliny', 'Kwiat z bukietu ślubnego zostaje w glinie. Roślina znika, rysunek zostaje.', $lowestPrice($settings->get('imprint_service_prices'))] : null,
    ]);

    $workshopsUrl = $link('workshops.index');
    $workshops = array_slice((array) $settings->get('workshop_types', []), 0, 3);

    $cards = array_filter([
        $link('custom-orders.index') ? [$link('custom-orders.index'), true, 'na zamówienie', 'Zaprojektujmy to razem', 'Serwis na prezent, chrzciny, wesele albo jedna rzecz, której nigdzie nie ma.'] : null,
        $link('content.b2b') ? [$link('content.b2b'), false, 'dla lokali', 'Ceramika dla kawiarni i restauracji', 'Powtarzalne formy, które wytrzymują zmywarkę i codzienny serwis.'] : null,
        $link('firing.index') ? [$link('firing.index'), false, 'zaplecze', 'Wypalę Twoje prace', 'Miejsce w piecu dla osób bez własnego zaplecza — biskwit i wypał na ostro.'] : null,
    ]);

    $strip = [
        'kasia-talerz-zloto.webp' => 'aspect-square',
        'misa-laguna.webp' => 'aspect-[3/4]',
        'kubek-nie-powinnam.webp' => 'aspect-square',
        'wazon-rzezbiony.webp' => 'aspect-square',
        'podstawki-muszle-roz.webp' => 'aspect-[3/4]',
        'talerz-niebieski-odcisk.webp' => 'aspect-square',
        'patera-czarna-kolce.webp' => 'aspect-square',
        'zestaw-flatlay.webp' => 'aspect-[3/4]',
    ];
    $instagramHandle = ltrim((string) $settings->get('instagram_handle'), '@');
@endphp

<x-shared::layout
    title="MellowAura — ceramika i rękodzieło z jedwabiu, Kraków"
    description="Ręcznie formowana ceramika i jedwabne dodatki z drugiego obiegu. Kubki z wbijanym napisem, warsztaty w kameralnej pracowni w Krakowie. Płatność BLIK."
    :canonical="route('home')"
    :image="$heroImage?->getAvailableUrl(['card'])"
>
    <div class="animate-ma-view pb-24">
        <div class="mx-auto max-w-[1280px] px-7">
            <div class="flex flex-wrap items-center gap-14 pt-18 pb-21">
                <div class="min-w-0 flex-[1_1_400px] animate-ma-hero-text">
                    <div class="mb-[26px] flex items-center gap-3">
                        <span class="h-px w-[34px] bg-dash"></span>
                        <span class="text-[10.5px] tracking-[0.3em] text-brown uppercase">home studio &middot; kraków</span>
                    </div>
                    <h1 class="mb-7 font-serif text-[length:clamp(42px,6.2vw,84px)] leading-[0.98] font-light tracking-[-0.02em] text-pretty">Glina i jedwab<br>z <em class="text-brown italic">jednej pary</em> rąk.</h1>
                    <p class="mb-9 max-w-[46ch] text-[17.5px] leading-[1.68] text-pretty text-lead">Ceramika formowana w dłoniach i jedwabne dodatki szyte z tkanin z drugiego obiegu — dwie dziedziny w jednej krakowskiej pracowni. Do tego kubki z napisem wbijanym stemplem, którego nie zetrze żadna zmywarka.</p>
                    <div class="mb-11 flex flex-wrap gap-3.5">
                        @if ($mugUrl = $link('mug.index'))
                            <a href="{{ $mugUrl }}" class="rounded-full bg-ink px-8 py-4 text-[14px] tracking-[0.04em] text-linen transition-[background-color,transform] duration-300 hover:bg-navy hover:text-linen active:scale-[.97]">Zaprojektuj kubek z napisem</a>
                        @endif
                        <a href="{{ route('shop.index') }}" class="rounded-full border border-line-strong px-8 py-4 text-[14px] tracking-[0.04em] text-ink transition-[border-color,background-color,transform] duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.98]">Zobacz produkty</a>
                        @if ($workshopsUrl)
                            <a href="{{ $workshopsUrl }}" class="rounded-full border border-line-strong px-8 py-4 text-[14px] tracking-[0.04em] text-ink transition-[border-color,background-color,transform] duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.98]">Warsztaty</a>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-8 border-t border-divider pt-7">
                        @foreach (array_filter([['100%', 'ręczna robota'], ['BLIK', 'płatność w 10 sekund'], ($dispatch = DispatchTime::label($settings, short: true)) ? [$dispatch, 'wysyłka zamówienia'] : null]) as [$value, $label])
                            <div>
                                <div class="font-serif text-[30px] leading-none">{{ $value }}</div>
                                <div class="mt-1 text-[11.5px] tracking-[0.14em] text-label uppercase">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($hero)
                    <a href="{{ route('product.show', $hero) }}" class="group relative block min-w-0 flex-[1_1_380px] animate-ma-hero-image text-ink hover:text-ink">
                        <div class="overflow-hidden rounded-[46%_54%_52%_48%/50%_46%_54%_50%] bg-line-soft shadow-hero">
                            @if ($heroImage)
                                <img src="{{ $heroImage->getAvailableUrl(['card']) }}" alt="Ręcznie formowana ceramika z pracowni MellowAura" fetchpriority="high" class="block aspect-square w-full object-cover transition-transform duration-[1.4s] ease-clay group-hover:scale-[1.04]">
                            @else
                                <div class="aspect-square w-full"></div>
                            @endif
                        </div>
                        <div class="absolute -bottom-3.5 -left-2.5 rounded-[18px] border border-divider bg-cream px-[18px] py-3.5 shadow-hero-badge transition-colors duration-300 group-hover:border-ink">
                            @if ($heroBadge)
                                <div class="text-[10px] tracking-[0.22em] text-gold uppercase">{{ $heroBadge }}</div>
                            @endif
                            <div class="mt-0.5 font-serif text-[19px]">{{ $hero->name }}</div>
                            <div class="text-[13px] text-muted"><x-catalog::price-label :product="$hero" /></div>
                        </div>
                    </a>
                @endif
            </div>
        </div>

        <div class="overflow-hidden border-y border-divider bg-sand-dark py-5 [mask-image:linear-gradient(90deg,transparent,#000_12%,#000_88%,transparent)]">
            <div aria-hidden="true" class="flex w-max animate-ma-marquee gap-10 pr-10 text-[12px] tracking-[0.18em] text-label uppercase">
                @foreach ([1, 2] as $copy)
                    @foreach (['glina', 'jedwab', 'len', 'złoto 24k', 'less waste', 'unikat', 'kraków'] as $word)
                        <span>{{ $word }}</span><span class="text-line-strong">✦</span>
                    @endforeach
                @endforeach
            </div>
        </div>

        @if ($featured->isNotEmpty())
            <section class="ma-reveal mx-auto max-w-[1280px] px-7 pt-21">
                <div class="mb-[34px] flex flex-wrap items-end justify-between gap-[18px]">
                    <h2 class="font-serif text-[length:clamp(30px,3.6vw,46px)] leading-[normal] font-light tracking-[-0.01em]">Co teraz jest w pracowni</h2>
                    <a href="{{ route('shop.index') }}" class="border-b border-line-strong pb-[3px] text-[13.5px] tracking-[0.06em]">Wszystkie produkty →</a>
                </div>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(230px,1fr))] gap-[26px]">
                    @foreach ($featured as $product)
                        <x-catalog::product-card :product="$product" :delay="$loop->index * 0.09" :with-variant-count="false" />
                    @endforeach
                </div>
            </section>
        @endif

        <section class="ma-reveal mx-auto max-w-[1280px] px-7 pt-21">
            <div class="flex flex-wrap items-center gap-12 overflow-hidden rounded-[4px] border border-divider bg-cream">
                <div class="min-w-0 flex-[1_1_320px] self-stretch">
                    <img src="{{ Vite::asset('zdjecia/kasia-talerz-zloto.webp') }}" alt="Kasia z talerzem zdobionym złotem" loading="lazy" class="block size-full min-h-[380px] object-cover">
                </div>
                <div class="min-w-0 flex-[1_1_380px] px-[26px] py-11 md:py-13 md:pr-13 md:pl-1">
                    <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">Cześć, jestem Kasia</div>
                    @if ($kasiaHeading)
                        <h2 class="mb-[22px] font-serif text-[length:clamp(28px,3.4vw,42px)] leading-[1.12] font-light">{{ $kasiaHeading }}</h2>
                    @endif
                    @if ($kasiaParagraph)
                        <p class="mb-[26px] text-[16.5px] leading-[1.7] text-pretty text-lead">{{ $kasiaParagraph }}</p>
                    @endif
                    @if ($aboutUrl = $link('content.about'))
                        <a href="{{ $aboutUrl }}" class="border-b border-line-strong pb-[3px] text-[13.5px] tracking-[0.06em]">Poznaj mnie bliżej →</a>
                    @endif
                </div>
            </div>
        </section>

        @if (($bundlesUrl = $link('bundles.index')) || ($giftsUrl = $link('gifts.index')))
            <section class="ma-reveal mx-auto max-w-[1280px] px-7 pt-21">
                <div class="flex flex-wrap overflow-hidden rounded-[4px] border border-divider bg-cream">
                    <div class="flex min-w-0 flex-[1_1_300px]">
                        <img src="{{ Vite::asset('zdjecia/kubek-cappuccino.webp') }}" alt="Kubek malowany ręcznie" loading="lazy" class="block aspect-square w-1/2 object-cover">
                        <img src="{{ Vite::asset('zdjecia/scrunchies-fiolet.webp') }}" alt="Jedwabne scrunchies" loading="lazy" class="block aspect-square w-1/2 object-cover">
                    </div>
                    <div class="flex min-w-0 flex-[1_1_360px] flex-col justify-center px-10 py-11">
                        <div class="mb-[18px] text-[10.5px] tracking-[0.3em] text-brown uppercase">zestawy prezentowe</div>
                        <h2 class="mb-4 font-serif text-[length:clamp(27px,3.2vw,38px)] leading-[1.14] font-light">Kubek i scrunchie<br>w jednym odcieniu</h2>
                        <p class="mb-6 max-w-[46ch] text-[16px] leading-[1.66] text-lead">Dobieram szkliwo do tkaniny, nie odwrotnie. Zestawu, w którym glina i jedwab mają ten sam kolor, nie kupisz w pracowni, która robi tylko jedno z dwóch.</p>
                        <div class="flex flex-wrap gap-3.5">
                            @if ($bundlesUrl)
                                <a href="{{ $bundlesUrl }}" class="rounded-full bg-ink px-[30px] py-[15px] text-[14px] text-linen transition-[background-color,transform] duration-300 hover:bg-navy hover:text-linen active:scale-[.97]">Zobacz zestawy</a>
                            @endif
                            @if ($giftsUrl = $link('gifts.index'))
                                <a href="{{ $giftsUrl }}" class="rounded-full border border-line-strong px-[30px] py-[15px] text-[14px] text-ink transition-[border-color,background-color,transform] duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.98]">Szukam prezentu</a>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @if ($services)
            <section class="ma-reveal mx-auto max-w-[1280px] px-7 pt-21">
                <div class="mb-3.5 text-[10.5px] tracking-[0.3em] text-brown uppercase">z Twojego</div>
                <h2 class="mb-2 font-serif text-[length:clamp(30px,3.6vw,46px)] leading-[normal] font-light">Przyślij mi coś swojego</h2>
                <p class="mb-[30px] max-w-[58ch] text-[16.5px] text-muted">Dwie usługi, których nie zamówisz w żadnej innej pracowni — bo wymagają i gliny, i igły.</p>
                <div class="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-[22px]">
                    @foreach ($services as [$url, $photo, $alt, $title, $text, $from])
                        <a href="{{ $url }}" class="flex flex-wrap overflow-hidden rounded-[4px] border border-divider bg-cream text-ink transition-[transform,box-shadow] duration-350 hover:-translate-y-1 hover:text-ink hover:shadow-card-hover">
                            <img src="{{ Vite::asset('zdjecia/'.$photo) }}" alt="{{ $alt }}" loading="lazy" class="block aspect-[3/4] w-[118px] flex-none object-cover">
                            <div class="min-w-0 flex-[1_1_180px] px-6 py-[26px]">
                                <div class="mb-2 font-serif text-[25px] leading-[1.16]">{{ $title }}</div>
                                <div class="text-[14.5px] leading-[1.6] text-muted">{{ $text }}</div>
                                @if ($from)
                                    <div class="mt-3 text-[13.5px] text-brown">od {{ Money::format((int) $from) }} →</div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="mt-21 overflow-hidden [mask-image:linear-gradient(90deg,transparent,#000_8%,#000_92%,transparent)]">
            <div aria-hidden="true" class="flex h-[clamp(180px,22vw,300px)] w-max animate-ma-strip gap-4 pr-4">
                @foreach ([1, 2] as $copy)
                    @foreach ($strip as $photo => $ratio)
                        <img src="{{ Vite::asset('zdjecia/'.$photo) }}" alt="" loading="lazy" class="block h-full rounded-[4px] object-cover {{ $ratio }}">
                    @endforeach
                @endforeach
            </div>
        </div>

        @if ($workshopsUrl && $workshops)
            <section class="ma-reveal mx-auto max-w-[1280px] px-7 pt-14">
                <h2 class="mb-2 font-serif text-[length:clamp(30px,3.6vw,46px)] leading-[normal] font-light">Zanurz dłonie w glinie</h2>
                <p class="mb-[34px] max-w-[58ch] text-[16.5px] text-muted">Kameralne warsztaty w mojej pracowni. Ciepło, empatia, cierpliwość — i nauka słuchania siebie.</p>
                <div class="grid grid-cols-[repeat(auto-fit,minmax(250px,1fr))] gap-[22px]">
                    @foreach ($workshops as $workshop)
                        <a href="{{ $workshopsUrl }}" class="flex flex-col gap-3 rounded-[4px] border border-divider bg-cream px-[26px] py-[30px] text-ink transition-[transform,box-shadow] duration-350 hover:-translate-y-1 hover:text-ink hover:shadow-card-hover">
                            <div class="text-[10px] tracking-[0.22em] text-gold uppercase">{{ $workshop['duration_label'] ?? '' }}</div>
                            <div class="font-serif text-[25px] leading-[1.15]">{{ $workshop['name'] ?? '' }}</div>
                            <div class="flex-1 text-[14.5px] leading-[1.6] text-muted">{{ $workshop['summary'] ?? '' }}</div>
                            @isset($workshop['price_gross'])
                                <div class="border-t border-divider pt-3 text-[15px] text-ink">od {{ Money::format((int) $workshop['price_gross']) }} / {{ $workshop['unit_label'] ?? '' }}</div>
                            @endisset
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Photos saved on the shop's own disk, so looking at them sends nothing to Instagram (privacy policy §9). --}}
        @if ($instagramPosts->isNotEmpty())
            <section class="ma-reveal mx-auto max-w-[1280px] px-7 pt-21">
                <div class="mb-[26px] flex flex-wrap items-end justify-between gap-5">
                    <div>
                        @if ($instagramHandle !== '')
                            <div class="mb-3 text-[10.5px] tracking-[0.3em] text-brown uppercase">{{ '@'.$instagramHandle }}</div>
                        @endif
                        <h2 class="font-serif text-[length:clamp(30px,3.6vw,46px)] leading-[normal] font-light">Codzienność pracowni <span aria-hidden="true" class="text-brown">🪐</span></h2>
                    </div>
                    @if ($instagramHandle !== '')
                        <a href="https://www.instagram.com/{{ $instagramHandle }}/" target="_blank" rel="noopener" class="border-b border-line-strong pb-[3px] text-[13.5px] tracking-[0.06em]">Obserwuj na Instagramie →</a>
                    @endif
                </div>
                <div class="rounded-[4px] border border-divider bg-cream p-3.5">
                    <div class="mb-3.5 flex flex-wrap items-center justify-between gap-3 border-b border-sand-dark px-1.5 pt-1 pb-3.5">
                        <div class="flex items-center gap-2.5">
                            <span class="size-2 animate-ma-pulse rounded-full bg-rose"></span>
                            <span class="text-[11.5px] tracking-[0.14em] text-label uppercase">prosto z Instagrama</span>
                        </div>
                        <span class="text-[12px] text-hint">ostatni post {{ $instagramPosts->first()->posted_at->diffForHumans() }}</span>
                    </div>
                    {{-- Six photos always fill whole rows: two, three or six to a row. --}}
                    <div class="grid grid-cols-2 gap-2.5 min-[520px]:grid-cols-3 min-[980px]:grid-cols-6">
                        @foreach ($instagramPosts as $post)
                            <a href="{{ $post->permalink }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-[3px] bg-line-soft">
                                <img src="{{ $post->imageUrl() }}" alt="{{ $post->alt() }}" loading="lazy" class="block aspect-square w-full object-cover transition-opacity duration-300 hover:opacity-72">
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($cards)
            <section class="ma-reveal mx-auto max-w-[1280px] px-7 pt-21">
                <div class="grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-[22px]">
                    @foreach ($cards as [$url, $dark, $eyebrow, $title, $text])
                        <a href="{{ $url }}" @class([
                            'flex min-h-[230px] flex-col justify-between rounded-[4px] px-8 py-[38px] transition-[background-color,border-color,transform] duration-300',
                            'bg-ink text-sand hover:bg-navy hover:text-sand active:scale-[.97]' => $dark,
                            'border border-divider bg-cream text-ink hover:border-ink hover:text-ink active:scale-[.98]' => ! $dark,
                        ])>
                            <div @class(['text-[10px] tracking-[0.24em] uppercase', 'text-line-strong' => $dark, 'text-gold' => ! $dark])>{{ $eyebrow }}</div>
                            <div>
                                <div class="mb-2.5 font-serif text-[29px] leading-[1.15]">{{ $title }}</div>
                                <div @class(['text-[14.5px] leading-[1.6]', 'text-on-dark' => $dark, 'text-muted' => ! $dark])>{{ $text }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-shared::layout>
