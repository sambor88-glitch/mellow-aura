@use('App\Modules\Catalog\Support\ProductPhoto')
@use('App\Modules\Shared\Support\DispatchTime')
@use('App\Modules\Shared\Support\Money')
@use('Illuminate\Support\Facades\Vite')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    // A section or button that leads to another page shows up once that page has a route.
    $link = fn (string $route) => Route::has($route) ? route($route) : null;
    $photo = fn (string $file) => Vite::asset('zdjecia/'.$file);

    $heroImage = $hero?->getFirstMedia('images');
    $heroBadge = $settings->get('home_hero_badge');
    $kasiaHeading = $settings->get('text_home_kasia_heading');
    $kasiaParagraph = $settings->get('text_home_kasia_paragraph');

    $lowestPrice = fn (mixed $rows) => collect(is_array($rows) ? $rows : [])->pluck('price_gross')->filter()->min();
    $services = array_filter([
        $link('content.scarf') ? [$link('content.scarf'), 'opaska-jedwab.webp', 'Opaska z jedwabnej apaszki', 'jedwab', 'Z Twojej apaszki', 'Apaszka po babci wraca jako opaska i dwie scrunchies. Resztki oddaję.', $lowestPrice($settings->get('scarf_service_prices'))] : null,
        $link('content.imprint') ? [$link('content.imprint'), 'talerz-niebieski-odcisk.webp', 'Talerz z odciskiem prawdziwej rośliny', 'glina', 'Odcisk Twojej rośliny', 'Kwiat z bukietu ślubnego zostaje w glinie. Roślina znika, rysunek zostaje.', $lowestPrice($settings->get('imprint_service_prices'))] : null,
    ]);

    $workshopsUrl = $link('workshops.index');
    $workshops = array_slice((array) $settings->get('workshop_types', []), 0, 3);

    $cards = array_filter([
        $link('custom-orders.index') ? [$link('custom-orders.index'), true, 'na zamówienie', 'Zaprojektujmy to razem', 'Serwis na prezent, chrzciny, wesele albo jedna rzecz, której nigdzie nie ma.'] : null,
        $link('content.b2b') ? [$link('content.b2b'), false, 'dla lokali', 'Ceramika dla kawiarni i restauracji', 'Powtarzalne formy, które wytrzymują zmywarkę i codzienny serwis.'] : null,
        $link('firing.index') ? [$link('firing.index'), false, 'zaplecze', 'Wypalę Twoje prace', 'Miejsce w piecu dla osób bez własnego zaplecza — biskwit i wypał na ostro.'] : null,
    ]);

    // Photos floating around the hero frame: file, class, scroll speed, depth under the mouse.
    $floats = [
        ['misa-laguna.webp', 'aura-float-1', 1.4, 30],
        ['podstawki-muszle-roz.webp', 'aura-float-3', 1.1, 22],
        ['opaska-jedwab.webp', 'aura-float-5', 2.2, 40],
        ['kubek-cappuccino.webp', 'aura-float-2', 0.9, 26],
        ['wazon-rzezbiony.webp', 'aura-float-4', 1.8, 46],
        ['kadzielnica-pszczoly.webp', 'aura-float-6', 1.3, 34],
    ];
    $instagramHandle = ltrim((string) $settings->get('instagram_handle'), '@');
    $dispatch = DispatchTime::label($settings, short: true);

    $btnMain = 'fill-btn aura-btn-main inline-flex min-h-[52px] items-center gap-2.5 rounded-full bg-ink px-[30px] text-[14px] font-medium tracking-[0.04em] text-linen transition-[background-color,color] duration-300 [--fill:var(--color-navy)] hover:bg-navy hover:text-linen active:scale-[.97]';
    $btnGlass = 'fill-btn aura-btn-glass glass inline-flex min-h-[52px] items-center gap-2.5 rounded-full px-[30px] text-[14px] font-medium tracking-[0.04em] text-ink transition-[border-color,color] duration-300 [--fill:var(--color-cream)] hover:border-ink hover:text-ink active:scale-[.97]';
    $sectionTitle = 'font-serif text-[length:clamp(40px,5.6vw,84px)] leading-[.98] font-light tracking-[-0.02em] text-balance';
@endphp

<x-shared::layout
    title="MellowAura — ceramika i rękodzieło z jedwabiu, Kraków"
    description="Ręcznie formowana ceramika i jedwabne dodatki z drugiego obiegu. Kubki z wbijanym napisem, warsztaty w kameralnej pracowni w Krakowie. Płatność BLIK."
    :canonical="route('home')"
    :image="$heroImage?->getAvailableUrl(['card'])"
>
    {{-- Firing intro on the first visit (resources/js/aura.js). No temperature: it depends on the clay, which we do not name. --}}
    <div id="aura-intro" class="aura-intro" hidden aria-hidden="true">
        <div class="aura-heat"></div>
        <div data-intro-core class="relative px-5 text-center">
            <div class="font-serif text-[length:clamp(96px,20vw,260px)] leading-[.9] font-light tracking-[-0.04em] tabular-nums"><span data-intro-number>0</span><sup class="ml-[.1em] align-[.25em] text-[.22em] tracking-normal text-brown italic">%</sup></div>
            <div data-intro-phase class="mt-[18px] min-h-[1.6em] text-[11px] tracking-[0.32em] text-graphite uppercase">surowa glina</div>
            <div class="mx-auto mt-[22px] h-px w-[min(260px,60vw)] overflow-hidden bg-ink/15"><i data-intro-bar class="block h-full origin-left scale-x-0 bg-ink"></i></div>
        </div>
        <button type="button" data-intro-skip class="absolute right-5 bottom-5 flex min-h-11 items-center rounded-full border border-line-strong px-4 text-[13.5px] text-ink">Pomiń</button>
    </div>

    {{-- The hero slides under the floating header. --}}
    <section id="aura-hero" class="aura-hero relative -mt-[78px]" aria-labelledby="hero-title">
        <div class="aura-hero-pin">
            <div class="aura-scene">
                @foreach ($floats as [$file, $class, $speed, $depth])
                    <div class="aura-float {{ $class }}" data-speed="{{ $speed }}" data-depth="{{ $depth }}" data-clay aria-hidden="true">
                        <div class="size-full"><img src="{{ $photo($file) }}" alt="" width="400" height="500" @if ($loop->index > 2) loading="lazy" @endif></div>
                    </div>
                @endforeach

                <div class="aura-frame">
                    <img src="{{ $photo('kasia-talerz-zloto.webp') }}" alt="Kasia w pracowni trzyma talerz ze złoconą krawędzią" fetchpriority="high" width="1200" height="1500"
                         class="size-full origin-[50%_75%] scale-[1.28] object-cover object-[50%_82%]">
                </div>
                <div class="aura-scrim"></div>

                <div class="aura-mark" aria-hidden="true">
                    <div class="aura-mark-1"><span class="inline-block">@foreach (mb_str_split('Mellow') as $i => $letter)<span class="blur-in" style="animation-delay: {{ .15 + $i * .06 }}s">{{ $letter }}</span>@endforeach</span></div>
                    <div class="aura-mark-2"><span class="inline-block">@foreach (mb_str_split('Aura') as $i => $letter)<span class="blur-in" style="animation-delay: {{ .55 + $i * .07 }}s">{{ $letter }}</span>@endforeach</span></div>
                </div>

                <div data-hero-meta class="absolute inset-x-0 bottom-[22px] z-6 flex items-end justify-between px-[clamp(18px,4vw,48px)] text-[10.5px] tracking-[0.3em] text-label uppercase">
                    <span>ceramika &amp; jedwab &middot; home studio &middot; kraków</span>
                    <span class="hidden items-center gap-3 sm:flex">przewiń <i class="h-[42px] w-px bg-linear-to-b from-brown to-transparent"></i></span>
                </div>
            </div>

            <div class="aura-hero-copy relative">
                <div data-hero-copy class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] pt-14 pb-5">
                    <p class="eyebrow mb-[22px]">home studio &middot; kraków</p>
                    <h1 id="hero-title" class="max-w-[12ch] font-serif text-[length:clamp(42px,6.4vw,92px)] leading-[.96] font-light tracking-[-0.02em] text-balance">Glina i jedwab z <em class="text-brown italic">jednej pary</em> rąk.</h1>
                    <p class="aura-lead mt-6 max-w-[46ch] text-[17.5px] leading-[1.68] text-pretty text-lead">Ceramika formowana w dłoniach i jedwabne dodatki szyte z tkanin z drugiego obiegu — dwie dziedziny w jednej krakowskiej pracowni. Do tego kubki z napisem wbijanym stemplem, którego nie zetrze żadna zmywarka.</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        @if ($mugUrl = $link('mug.index'))
                            <a href="{{ $mugUrl }}" data-magnet class="{{ $btnMain }}">Zaprojektuj kubek z napisem <span aria-hidden="true">→</span></a>
                        @endif
                        <a href="{{ route('shop.index') }}" data-magnet class="{{ $btnGlass }}">Zobacz produkty</a>
                        @if ($workshopsUrl)
                            <a href="{{ $workshopsUrl }}" data-magnet class="{{ $btnGlass }}">Warsztaty</a>
                        @endif
                    </div>
                    <div class="mt-8 flex flex-wrap items-stretch gap-3">
                        @foreach (array_filter([['100%', 'ręczna robota'], ['BLIK', 'płatność w 10 sekund'], $dispatch ? [$dispatch, 'wysyłka zamówienia'] : null]) as [$value, $label])
                            <div class="aura-fact glass flex flex-col gap-1 rounded-2xl px-5 py-3.5">
                                <strong class="font-serif text-[26px] leading-none font-light">{{ $value }}</strong>
                                <span class="aura-fact-label text-[10.5px] tracking-[0.16em] text-label uppercase">{{ $label }}</span>
                            </div>
                        @endforeach
                        @if ($hero)
                            <a href="{{ route('product.show', $hero) }}" class="aura-fact glass flex flex-col gap-1 rounded-2xl px-5 py-3.5 text-[inherit] transition-colors duration-300 hover:border-ink hover:text-[inherit]">
                                @if ($heroBadge)
                                    <span class="self-start rounded-full bg-rose px-2.5 py-0.5 text-[11px] tracking-[0.06em] text-ink">{{ $heroBadge }}</span>
                                @endif
                                <strong class="font-serif text-[21px] leading-tight font-light">{{ $hero->name }}</strong>
                                <span class="aura-fact-label text-[13px] text-label"><x-catalog::price-label :product="$hero" /></span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($kasiaHeading || $kasiaParagraph)
        <section class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] py-[clamp(110px,18vh,200px)]" aria-labelledby="kasia-title">
            <div class="flex flex-wrap items-end gap-14">
                <div class="min-w-0 flex-[1_1_520px]">
                    <p id="kasia-title" class="eyebrow mb-[22px]">Cześć, jestem Kasia</p>
                    @if ($kasiaHeading)
                        <h2 class="mb-8 max-w-[18ch] font-serif text-[length:clamp(34px,4.4vw,64px)] leading-[1.04] font-light tracking-[-0.01em] text-balance">{{ $kasiaHeading }}</h2>
                    @endif
                    @if ($kasiaParagraph)
                        <p data-aura-words class="max-w-[30ch] font-serif text-[length:clamp(26px,3.2vw,44px)] leading-[1.2] font-light text-graphite">{{ $kasiaParagraph }}</p>
                    @endif
                    <div class="mt-12 flex flex-wrap items-center gap-x-8 gap-y-5">
                        <span class="font-serif text-[length:clamp(34px,4vw,54px)] leading-none font-light text-brown italic">— Kasia</span>
                        @if ($aboutUrl = $link('content.about'))
                            <a href="{{ $aboutUrl }}" data-magnet class="{{ $btnGlass }}">Poznaj mnie bliżej <span aria-hidden="true">→</span></a>
                        @endif
                    </div>
                </div>
                <div data-clay class="aspect-[3/4] min-w-0 flex-[0_1_300px] overflow-hidden rounded-[200px_200px_16px_16px]">
                    <img src="{{ $photo('zestaw-filizanka-talerz.webp') }}" alt="Filiżanka z talerzykiem z pracowni MellowAura" loading="lazy" width="600" height="800" data-aura-parallax="8" class="size-full object-cover">
                </div>
            </div>
        </section>
    @endif

    <div aria-hidden="true" class="overflow-hidden pt-8 pb-24">
        @foreach ([['left', '', ['glina', 'jedwab', 'len', 'złoto 24k'], 1], ['right', 'aura-row-outline', ['surowość', 'miękkość', 'less waste', 'unikat', 'kraków'], 0]] as [$direction, $class, $words, $italic])
            <div data-aura-row="{{ $direction }}" class="aura-row {{ $class }}">
                @foreach ([1, 2, 3] as $copy)
                    @foreach ($words as $i => $word)
                        <span class="pr-[.4em] {{ $i % 2 === $italic ? 'text-brown italic' : '' }}"><i class="mr-[.4em] inline-block size-[.18em] rounded-full bg-rose align-middle"></i>{{ $word }}</span>
                    @endforeach
                @endforeach
            </div>
        @endforeach
    </div>

    @if ($featured->isNotEmpty())
        <section data-aura-shelf class="relative flex flex-col justify-center py-16 md:min-h-svh" aria-labelledby="shop-title">
            <div class="mx-auto mb-9 flex w-full max-w-[1360px] flex-wrap items-end justify-between gap-x-10 gap-y-5 px-[clamp(18px,4vw,48px)]">
                <div>
                    <p class="eyebrow mb-[22px]">sklep</p>
                    <h2 id="shop-title" class="{{ $sectionTitle }}">Co teraz jest <em class="text-brown italic">w pracowni</em></h2>
                </div>
                <div aria-hidden="true" class="h-0.5 min-w-40 flex-[0_1_260px] self-center overflow-hidden rounded-full bg-line"><i data-shelf-progress class="block size-full origin-left scale-x-0 bg-ink"></i></div>
            </div>
            <div class="aura-track-wrap pb-3.5">
                <div data-shelf-track class="flex w-max gap-[clamp(16px,2vw,28px)] px-[clamp(18px,4vw,48px)]">
                    @foreach ($featured as $product)
                        <div class="w-[min(clamp(250px,26vw,360px),44vh)] flex-none snap-start">
                            <x-catalog::product-card :product="$product" :delay="$loop->index * 0.07" :with-variant-count="false" />
                        </div>
                    @endforeach
                    <a href="{{ route('shop.index') }}" class="grid aspect-[4/5] w-[min(clamp(250px,26vw,360px),44vh)] flex-none snap-start place-items-center self-start rounded-[18px] border border-dashed border-line-strong text-ink transition-[background-color,border-color] duration-400 hover:border-ink hover:bg-glass hover:text-ink">
                        <span class="p-5 text-center font-serif text-[32px] leading-[1.1] font-light italic">Wszystkie produkty →</span>
                    </a>
                </div>
            </div>
        </section>
    @endif

    @if (($bundlesUrl = $link('bundles.index')) || $link('gifts.index'))
        <section class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] pt-[clamp(90px,14vh,150px)]" aria-labelledby="gifts-title">
            <div class="glass flex flex-wrap overflow-hidden rounded-[26px]">
                <div class="flex min-w-0 flex-[1_1_300px] gap-1.5 p-1.5">
                    @foreach ([['kubek-cappuccino.webp', 'Kubek malowany ręcznie'], ['scrunchies-fiolet.webp', 'Jedwabne scrunchies']] as [$file, $alt])
                        <div data-clay class="aspect-square w-1/2 overflow-hidden rounded-[20px]"><img src="{{ $photo($file) }}" alt="{{ $alt }}" loading="lazy" width="600" height="600" data-aura-parallax="6" class="size-full object-cover"></div>
                    @endforeach
                </div>
                <div class="flex min-w-0 flex-[1_1_360px] flex-col justify-center px-[clamp(24px,4vw,48px)] py-11">
                    <p class="eyebrow mb-[18px]">zestawy prezentowe</p>
                    <h2 id="gifts-title" class="mb-4 font-serif text-[length:clamp(32px,3.8vw,52px)] leading-[1.04] font-light">Kubek i scrunchie <em class="text-brown italic">w jednym odcieniu</em></h2>
                    <p class="mb-7 max-w-[46ch] text-[16.5px] leading-[1.66] text-lead">Dobieram szkliwo do tkaniny, nie odwrotnie. Zestawu, w którym glina i jedwab mają ten sam kolor, nie kupisz w pracowni, która robi tylko jedno z dwóch.</p>
                    <div class="flex flex-wrap gap-3">
                        @if ($bundlesUrl)
                            <a href="{{ $bundlesUrl }}" data-magnet class="{{ $btnMain }}">Zobacz zestawy <span aria-hidden="true">→</span></a>
                        @endif
                        @if ($giftsUrl = $link('gifts.index'))
                            <a href="{{ $giftsUrl }}" data-magnet class="{{ $btnGlass }}">Szukam prezentu</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($services)
        <section class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] pt-[clamp(90px,14vh,150px)]" aria-labelledby="own-title">
            <div class="mb-10 flex flex-wrap items-end justify-between gap-x-10 gap-y-5">
                <div>
                    <p class="eyebrow mb-[22px]">z Twojego</p>
                    <h2 id="own-title" class="{{ $sectionTitle }}">Przyślij mi <em class="text-brown italic">coś swojego</em></h2>
                </div>
                <p class="m-0 max-w-[40ch] text-[16.5px] text-lead">Dwie usługi, których nie zamówisz w żadnej innej pracowni — bo wymagają i gliny, i igły.</p>
            </div>
            <div data-aura-rise class="flex flex-wrap gap-[clamp(16px,2vw,28px)]">
                @foreach ($services as [$url, $file, $alt, $material, $title, $text, $from])
                    <a href="{{ $url }}" data-clay class="group relative block h-[clamp(460px,72vh,700px)] min-w-0 flex-[1_1_420px] overflow-hidden rounded-[24px] text-sand hover:text-sand focus-visible:outline-offset-4">
                        <img src="{{ $photo($file) }}" alt="{{ $alt }}" loading="lazy" width="900" height="1200" data-aura-parallax="6" class="absolute inset-x-0 -top-[8%] h-[116%] w-full object-cover">
                        <span class="absolute inset-0 bg-linear-to-b from-transparent from-40% to-ink/55"></span>
                        <span class="glass-dark absolute inset-x-3.5 bottom-3.5 z-2 block rounded-[18px] px-6 py-[22px] transition-transform duration-800 ease-clay group-hover:-translate-y-2">
                            <span class="text-[10.5px] tracking-[0.26em] text-on-dark uppercase">{{ $material }}</span>
                            <span class="mt-2 block font-serif text-[length:clamp(28px,3vw,40px)] leading-[1.05] font-light text-sand">{{ $title }}</span>
                            <span class="mt-2 block max-w-[42ch] text-[14.5px] text-on-dark">{{ $text }}</span>
                            <span class="mt-3.5 block text-[14px] text-rose">{{ $from ? 'od '.Money::format((int) $from).' →' : 'Jak to działa →' }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($workshopsUrl && $workshops)
        <section class="focus-on-dark relative mt-[clamp(90px,14vh,150px)] overflow-hidden rounded-t-[36px] bg-ink py-[clamp(100px,16vh,170px)] text-on-dark" aria-labelledby="workshops-title">
            <div aria-hidden="true" class="aura-orb aura-orb-1 opacity-100 [background:radial-gradient(circle,rgb(214_163_156/.45),rgb(214_163_156/0)_65%)]"></div>
            <div aria-hidden="true" class="aura-orb aura-orb-2 opacity-100 [background:radial-gradient(circle,rgb(185_142_100/.4),rgb(185_142_100/0)_65%)]"></div>
            <div class="relative mx-auto flex max-w-[1360px] flex-wrap items-center gap-[clamp(40px,6vw,80px)] px-[clamp(18px,4vw,48px)]">
                <div class="min-w-0 flex-[1_1_440px]">
                    <p class="eyebrow mb-[22px] text-label-dark [--eyebrow-dash:var(--color-rose)]">warsztaty ceramiczne &middot; kraków</p>
                    <h2 id="workshops-title" class="font-serif text-[length:clamp(44px,6.2vw,96px)] leading-[.95] font-light tracking-[-0.02em] text-sand">Zanurz dłonie <em class="text-rose italic">w glinie</em></h2>
                    <p class="mt-6 max-w-[44ch] text-[17.5px] leading-[1.68] text-on-dark-muted">Kameralne warsztaty w mojej pracowni. Ciepło, empatia, cierpliwość — i nauka słuchania siebie.</p>
                    <div data-aura-rise class="mt-9 grid gap-2.5">
                        @foreach ($workshops as $workshop)
                            <a href="{{ $workshopsUrl }}" class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2 rounded-[18px] border border-linen/14 bg-linen/6 px-5 py-[18px] text-on-dark backdrop-blur-[16px] transition-[background-color,border-color] duration-300 hover:border-linen/40 hover:bg-linen/12 hover:text-on-dark">
                                <span class="min-w-0 flex-[1_1_240px]">
                                    @if (! empty($workshop['duration_label']))
                                        <span class="block text-[10.5px] tracking-[0.16em] text-label-dark uppercase">{{ $workshop['duration_label'] }}</span>
                                    @endif
                                    <span class="mt-1 block font-serif text-[25px] leading-[1.15] font-light text-sand">{{ $workshop['name'] ?? '' }}</span>
                                    @if (! empty($workshop['summary']))
                                        <span class="mt-1 block text-[14px] leading-[1.55] text-on-dark-muted">{{ $workshop['summary'] }}</span>
                                    @endif
                                </span>
                                @isset($workshop['price_gross'])
                                    <span class="font-serif text-[22px] font-light whitespace-nowrap text-sand">od {{ Money::format((int) $workshop['price_gross']) }} / {{ $workshop['unit_label'] ?? '' }}</span>
                                @endisset
                            </a>
                        @endforeach
                    </div>
                    <div class="mt-8">
                        <a href="{{ $workshopsUrl }}" data-magnet class="fill-btn inline-flex min-h-[52px] items-center gap-2.5 rounded-full bg-linen px-[30px] text-[14px] font-medium tracking-[0.04em] text-ink [--fill:var(--color-rose)] hover:bg-rose hover:text-ink">Wybierz termin <span aria-hidden="true">→</span></a>
                    </div>
                </div>
                <div aria-hidden="true" class="relative h-[clamp(460px,70vh,680px)] min-w-0 flex-[1_1_420px]">
                    @foreach ([['zestaw-flatlay.webp', 'top-[6%] left-0 h-[70%] w-[58%]'], ['patera-czarna-kolce.webp', 'top-0 right-0 h-[44%] w-[38%]'], ['kubek-nie-powinnam.webp', 'right-[6%] bottom-0 h-[46%] w-[46%]']] as [$file, $place])
                        <div data-clay class="absolute overflow-hidden rounded-[20px] {{ $place }}"><img src="{{ $photo($file) }}" alt="" loading="lazy" width="800" height="1000" data-aura-parallax="{{ 6 + $loop->index * 4 }}" class="size-full object-cover"></div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Photos saved on the shop's own disk, so looking at them sends nothing to Instagram (privacy policy §9). --}}
    @if ($instagramPosts->isNotEmpty())
        <section class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] pt-[clamp(90px,14vh,150px)]" aria-labelledby="instagram-title">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-5">
                <div>
                    @if ($instagramHandle !== '')
                        <p class="eyebrow mb-[22px]">{{ '@'.$instagramHandle }}</p>
                    @endif
                    <h2 id="instagram-title" class="{{ $sectionTitle }}">Codzienność pracowni <span aria-hidden="true" class="text-brown">🪐</span></h2>
                </div>
                @if ($instagramHandle !== '')
                    <a href="https://www.instagram.com/{{ $instagramHandle }}/" target="_blank" rel="noopener" data-magnet class="{{ $btnGlass }}">Obserwuj na Instagramie →</a>
                @endif
            </div>
            <div class="glass rounded-[26px] p-3.5">
                <div class="mb-3.5 flex flex-wrap items-center justify-between gap-3 border-b border-line px-1.5 pt-1 pb-3.5">
                    <div class="flex items-center gap-2.5">
                        <span class="size-2 animate-ma-pulse rounded-full bg-rose"></span>
                        <span class="text-[11.5px] tracking-[0.14em] text-label uppercase">prosto z Instagrama</span>
                    </div>
                    <span class="text-[12px] text-label">ostatni post {{ $instagramPosts->first()->posted_at->diffForHumans() }}</span>
                </div>
                {{-- Six photos always fill whole rows: two, three or six to a row. --}}
                <div data-aura-rise class="grid grid-cols-2 gap-2.5 min-[520px]:grid-cols-3 min-[980px]:grid-cols-6">
                    @foreach ($instagramPosts as $post)
                        <a href="{{ $post->permalink }}" target="_blank" rel="noopener" data-clay class="block overflow-hidden rounded-[14px] bg-linen">
                            <img src="{{ $post->imageUrl() }}" alt="{{ $post->alt() }}" loading="lazy" width="1080" height="1080" class="block aspect-square w-full object-cover transition-transform duration-[1.1s] ease-clay hover:scale-[1.06]">
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($cards)
        <section class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] pt-[clamp(90px,14vh,150px)] pb-[clamp(90px,14vh,150px)]">
            <div data-aura-rise class="grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-[clamp(16px,2vw,28px)]">
                @foreach ($cards as [$url, $dark, $eyebrow, $title, $text])
                    <a href="{{ $url }}" @class([
                        'fill-btn flex min-h-[260px] flex-col justify-between rounded-[24px] px-8 py-[38px] transition-[border-color,transform] duration-500 ease-clay hover:-translate-y-1',
                        'focus-on-dark bg-ink text-sand [--fill:var(--color-navy)] hover:bg-navy hover:text-sand' => $dark,
                        'glass text-ink [--fill:var(--color-cream)] hover:border-ink hover:text-ink' => ! $dark,
                    ])>
                        <span @class(['text-[10.5px] tracking-[0.24em] uppercase', 'text-rose' => $dark, 'text-brown' => ! $dark])>{{ $eyebrow }}</span>
                        <span>
                            <span class="mb-2.5 block font-serif text-[31px] leading-[1.1] font-light">{{ $title }}</span>
                            <span @class(['block text-[14.5px] leading-[1.6]', 'text-on-dark' => $dark, 'text-muted' => ! $dark])>{{ $text }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-shared::layout>
