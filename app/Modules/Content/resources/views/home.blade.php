@use('App\Modules\Catalog\Support\ProductPhoto')
@use('App\Modules\Shared\Support\DispatchTime')
@use('App\Modules\Shared\Support\Money')
@use('App\Modules\Localization\Support\Locales')
@inject('settings', 'App\Modules\Settings\Settings')
@inject('pagePhotos', 'App\Modules\Content\Support\PagePhotos')
@php
    // A section or button that leads to another page shows up once that page exists in the language of this page,
    // so the English home page has no sections for services held in Polish.
    $link = fn (string $route) => Locales::has($route, Locales::current()) ? route($route) : null;
    $t = fn (string $key, array $replace = []) => __('content::home.'.$key, $replace);
    // A heading with its words in italics: the whole line escaped, then „:em” swapped for the italic words.
    $em = fn (string $key, string $class = 'text-brown italic') => new Illuminate\Support\HtmlString(
        str_replace(':em', '<em class="'.$class.'">'.e($t($key.'_em')).'</em>', e($t($key)))
    );
    // Photos by place, from the panel's „Zdjęcia stron”; a place without a photo is left out.
    $photo = fn (string $place) => $pagePhotos->get($place);

    $heroImage = $hero?->getFirstMedia('images');
    // Kasia writes these in Polish in the panel. Until the panel has English versions (MA-126) an English page
    // leaves them out rather than showing Polish under an English address.
    $polish = Locales::current() === Locales::default();
    $heroBadge = $polish ? $settings->get('home_hero_badge') : null;
    $kasiaHeading = $polish ? $settings->get('text_home_kasia_heading') : null;
    $kasiaParagraph = $polish ? $settings->get('text_home_kasia_paragraph') : null;

    $lowestPrice = fn (mixed $rows) => collect(is_array($rows) ? $rows : [])->pluck('price_gross')->filter()->min();
    $services = array_filter([
        $link('content.scarf') ? [$link('content.scarf'), 'home-scarf', $t('scarf_alt'), $t('scarf_material'), $t('scarf_title'), $t('scarf_text'), $lowestPrice($settings->get('scarf_service_prices'))] : null,
        $link('content.imprint') ? [$link('content.imprint'), 'home-imprint', $t('imprint_alt'), $t('imprint_material'), $t('imprint_title'), $t('imprint_text'), $lowestPrice($settings->get('imprint_service_prices'))] : null,
    ]);

    $workshopsUrl = $link('workshops.index');
    $workshops = array_slice((array) $settings->get('workshop_types', []), 0, 3);

    $cards = array_filter([
        $link('custom-orders.index') ? [$link('custom-orders.index'), true, $t('card_custom_eyebrow'), $t('card_custom_title'), $t('card_custom_text')] : null,
        $link('content.b2b') ? [$link('content.b2b'), false, $t('card_b2b_eyebrow'), $t('card_b2b_title'), $t('card_b2b_text')] : null,
        $link('firing.index') ? [$link('firing.index'), false, $t('card_firing_eyebrow'), $t('card_firing_title'), $t('card_firing_text')] : null,
    ]);

    // Photos floating around the hero frame: place, class, scroll speed, depth under the mouse.
    $floats = [
        ['home-float-1', 'aura-float-1', 1.4, 30],
        ['home-float-2', 'aura-float-3', 1.1, 22],
        ['home-float-3', 'aura-float-5', 2.2, 40],
        ['home-float-4', 'aura-float-2', 0.9, 26],
        ['home-float-5', 'aura-float-4', 1.8, 46],
        ['home-float-6', 'aura-float-6', 1.3, 34],
    ];
    $heroPhoto = $photo('home-hero');
    $kasiaPhoto = $photo('home-kasia');
    $instagramHandle = ltrim((string) $settings->get('instagram_handle'), '@');
    $dispatch = DispatchTime::label($settings, short: true);

    $btnMain = 'fill-btn aura-btn-main inline-flex min-h-[52px] items-center gap-2.5 rounded-full bg-ink px-[30px] text-[14px] font-medium tracking-[0.04em] text-linen transition-[background-color,color] duration-300 [--fill:var(--color-rose)] hover:bg-rose hover:text-ink active:scale-[.97]';
    $btnGlass = 'fill-btn aura-btn-glass glass inline-flex min-h-[52px] items-center gap-2.5 rounded-full px-[30px] text-[14px] font-medium tracking-[0.04em] text-ink transition-[border-color,color] duration-300 [--fill:var(--color-cream)] hover:border-ink hover:text-ink active:scale-[.97]';
    $sectionTitle = 'font-serif text-[length:clamp(40px,5.6vw,84px)] leading-[.98] font-light tracking-[-0.02em] text-balance';
@endphp

<x-shared::layout
    :title="$t('title')"
    :description="$t('description')"
    :canonical="route('home')"
    :image="$heroImage?->getAvailableUrl(['card'])"
>
    {{-- The hero slides under the floating header. --}}
    <section id="aura-hero" class="aura-hero relative -mt-[78px]" aria-labelledby="hero-title">
        <div class="aura-hero-pin">
            <div class="aura-scene">
                {{-- Lazy: two of them are hidden on a phone and then never download, and the rest wait for the hero photo. --}}
                @foreach ($floats as [$place, $class, $speed, $depth])
                    @continue (! ($float = $photo($place))->exists())
                    <div class="aura-float {{ $class }}" data-speed="{{ $speed }}" data-depth="{{ $depth }}" aria-hidden="true">
                        <div class="size-full"><img src="{{ $float->url }}" {{ $float->srcsetAttributes('(max-width: 760px) 100px, 13vw') }} alt="" width="400" height="500" style="{{ $float->style() }}" loading="lazy"></div>
                    </div>
                @endforeach

                {{-- On a phone the frame shows only the middle half of the screen and the plate sits at the bottom of the photo,
                     so the photo starts a third higher; aura.js brings it back as the frame opens. The point in view is set
                     in the panel twice, for a computer and for a phone, where the frame is narrow and tall. --}}
                <div class="aura-frame">
                    @if ($heroPhoto->exists())
                        <img src="{{ $heroPhoto->url }}" {{ $heroPhoto->srcsetAttributes('(max-aspect-ratio: 3/4) 75vh, 100vw') }} alt="{{ $heroPhoto->alt($t('hero_alt')) }}" fetchpriority="high" width="1200" height="1500" style="{{ $heroPhoto->styleVariables() }}"
                             class="size-full origin-[50%_75%] scale-[1.28] object-cover object-(--focus) max-[760px]:-translate-y-[32%] max-[760px]:object-(--focus-phone)">
                    @endif
                </div>
                <div class="aura-scrim"></div>

                <div class="aura-mark" aria-hidden="true">
                    <div class="aura-mark-1"><span class="inline-block">@foreach (mb_str_split('Mellow') as $i => $letter)<span class="blur-in" style="animation-delay: {{ .05 + $i * .04 }}s">{{ $letter }}</span>@endforeach</span></div>
                    <div class="aura-mark-2"><span class="inline-block">@foreach (mb_str_split('Aura') as $i => $letter)<span class="blur-in" style="animation-delay: {{ .3 + $i * .04 }}s">{{ $letter }}</span>@endforeach</span></div>
                </div>

                {{-- The first screen says what this is and leads to the shop; it fades as the frame grows (aura.js).
                     On a phone it sits low and the button stays beside the line, so it never reaches the „Aura” under the frame. --}}
                <div data-hero-meta class="absolute inset-x-3 bottom-3 z-6 mx-auto flex w-auto max-w-[680px] items-center gap-3 rounded-[22px] border border-glass-line bg-cream/90 py-2 pr-2 pl-4 backdrop-blur-[18px] sm:inset-x-[clamp(18px,4vw,48px)] sm:bottom-[22px] sm:w-fit sm:gap-6 sm:rounded-full sm:pl-7">
                    <p class="min-w-0 flex-1 font-serif text-[length:clamp(15px,1.5vw,19px)] leading-[1.3] text-brown italic text-pretty">{{ $t('hero_bar') }}</p>
                    <a href="{{ route('shop.index') }}" class="{{ $btnMain }} flex-none max-sm:min-h-12! max-sm:px-[18px]! max-sm:text-[13.5px]!">{{ $t('hero_bar_cta') }} <span aria-hidden="true">→</span></a>
                </div>
            </div>

            <div class="aura-hero-copy relative">
                <div data-hero-copy class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] pt-14 pb-5">
                    <p class="eyebrow mb-[22px]">{{ $t('eyebrow') }}</p>
                    <h1 id="hero-title" class="max-w-[12ch] font-serif text-[length:clamp(42px,6.4vw,92px)] leading-[.96] font-light tracking-[-0.02em] text-balance">{{ $em('heading') }}</h1>
                    <p class="aura-lead mt-6 max-w-[46ch] text-[17.5px] leading-[1.68] text-pretty text-lead">{{ $t('lead') }}</p>
                    {{-- On a phone an even grid: the main button across, the others in two equal columns, a single one across. --}}
                    <div @class(['mt-8 grid grid-cols-2 gap-3 md:flex md:flex-wrap [&>*]:justify-center [&>*]:whitespace-nowrap max-md:[&>*]:px-4! [&>.aura-btn-main]:col-span-2', '[&>*:last-child]:col-span-2' => ! $workshopsUrl])>
                        @if ($mugUrl = $link('mug.index'))
                            <a href="{{ $mugUrl }}" data-magnet class="{{ $btnMain }}">{{ $t('cta_mug') }} <span aria-hidden="true">→</span></a>
                        @endif
                        <a href="{{ route('shop.index') }}" data-magnet class="{{ $btnGlass }}">{{ $t('cta_shop') }}</a>
                        @if ($workshopsUrl)
                            <a href="{{ $workshopsUrl }}" data-magnet class="{{ $btnGlass }}">{{ $t('cta_workshops') }}</a>
                        @endif
                    </div>
                    <div class="mt-8 grid max-w-[720px] grid-cols-2 items-stretch gap-3 md:auto-cols-fr md:grid-flow-col md:grid-cols-none [&>*:last-child:nth-child(odd)]:col-span-2 md:[&>*:last-child:nth-child(odd)]:col-span-1">
                        {{-- BLIK only where the price is in złoty: it does not exist in euro. --}}
                        @foreach (array_filter([['100%', $t('fact_handmade')], Locales::currency() === 'PLN' ? ['BLIK', $t('fact_blik')] : null, $dispatch ? [$dispatch, $t('fact_dispatch')] : null]) as [$value, $label])
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

    @if ($featured->isNotEmpty())
        <section data-aura-shelf class="relative flex flex-col justify-center py-16 md:min-h-svh" aria-labelledby="shop-title">
            <div class="mx-auto mb-9 flex w-full max-w-[1360px] flex-wrap items-end justify-between gap-x-10 gap-y-5 px-[clamp(18px,4vw,48px)]">
                <div>
                    <p class="eyebrow mb-[22px]">{{ $t('shop_eyebrow') }}</p>
                    <h2 id="shop-title" class="{{ $sectionTitle }}">{{ $em('shop_heading') }}</h2>
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
                        <span class="p-5 text-center font-serif text-[32px] leading-[1.1] font-light italic">{{ $t('shop_all') }}</span>
                    </a>
                </div>
            </div>
        </section>
    @endif

    @if ($kasiaHeading || $kasiaParagraph)
        <section class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] py-20 sm:py-[clamp(110px,18vh,200px)]" aria-labelledby="kasia-title">
            <div class="flex flex-wrap items-end gap-14">
                <div class="min-w-0 flex-[1_1_520px]">
                    <p id="kasia-title" class="eyebrow mb-[22px]">{{ $t('kasia_eyebrow') }}</p>
                    @if ($kasiaHeading)
                        <h2 class="mb-8 max-w-[18ch] font-serif text-[length:clamp(34px,4.4vw,64px)] leading-[1.04] font-light tracking-[-0.01em] text-balance">{{ $kasiaHeading }}</h2>
                    @endif
                    @if ($kasiaParagraph)
                        <p data-aura-words class="max-w-[30ch] font-serif text-[length:clamp(26px,3.2vw,44px)] leading-[1.2] font-light text-graphite">{{ $kasiaParagraph }}</p>
                    @endif
                    <div class="mt-12 flex flex-wrap items-center gap-x-8 gap-y-5">
                        <span class="font-serif text-[length:clamp(34px,4vw,54px)] leading-none font-light text-brown italic">{{ $t('kasia_signature') }}</span>
                        @if ($aboutUrl = $link('content.about'))
                            <a href="{{ $aboutUrl }}" data-magnet class="{{ $btnGlass }}">{{ $t('kasia_more') }} <span aria-hidden="true">→</span></a>
                        @endif
                    </div>
                </div>
                @if ($kasiaPhoto->exists())
                    <div class="aspect-[3/4] min-w-0 flex-[0_1_300px] overflow-hidden rounded-[200px_200px_16px_16px]">
                        <img src="{{ $kasiaPhoto->url }}" {{ $kasiaPhoto->srcsetAttributes('340px') }} alt="{{ $kasiaPhoto->alt($t('kasia_alt')) }}" loading="lazy" width="600" height="800" data-aura-parallax="8" style="{{ $kasiaPhoto->style() }}" class="size-full object-cover">
                    </div>
                @endif
            </div>
        </section>
    @endif

    <div aria-hidden="true" class="overflow-hidden pt-4 pb-12 sm:pb-16">
        @foreach ([['left', '', $t('words')[0], 1], ['right', 'aura-row-outline', $t('words')[1], 0]] as [$direction, $class, $words, $italic])
            <div data-aura-row="{{ $direction }}" class="aura-row {{ $class }}">
                @foreach ([1, 2, 3] as $copy)
                    @foreach ($words as $i => $word)
                        <span class="pr-[.4em] {{ $i % 2 === $italic ? 'text-brown italic' : '' }}"><i class="mr-[.4em] inline-block size-[.18em] rounded-full bg-rose align-middle"></i>{{ $word }}</span>
                    @endforeach
                @endforeach
            </div>
        @endforeach
    </div>

    @if (($bundlesUrl = $link('bundles.index')) || $link('gifts.index'))
        <section class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] pt-20 sm:pt-[clamp(90px,14vh,150px)]" aria-labelledby="gifts-title">
            <div class="glass flex flex-wrap overflow-hidden rounded-[26px]">
                <div class="flex min-w-0 flex-[1_1_300px] gap-1.5 p-1.5">
                    @foreach ([['home-gifts-1', $t('gifts_mug_alt')], ['home-gifts-2', $t('gifts_scrunchies_alt')]] as [$place, $alt])
                        @continue (! ($gift = $photo($place))->exists())
                        <div class="aspect-square w-1/2 overflow-hidden rounded-[20px]"><img src="{{ $gift->url }}" {{ $gift->srcsetAttributes('(min-width: 768px) 25vw, 50vw') }} alt="{{ $gift->alt($alt) }}" loading="lazy" width="600" height="600" data-aura-parallax="6" style="{{ $gift->style() }}" class="size-full object-cover"></div>
                    @endforeach
                </div>
                <div class="flex min-w-0 flex-[1_1_360px] flex-col justify-center px-[clamp(24px,4vw,48px)] py-11">
                    <p class="eyebrow mb-[18px]">{{ $t('gifts_eyebrow') }}</p>
                    <h2 id="gifts-title" class="mb-4 font-serif text-[length:clamp(32px,3.8vw,52px)] leading-[1.04] font-light">{{ $em('gifts_heading') }}</h2>
                    <p class="mb-7 max-w-[46ch] text-[16.5px] leading-[1.66] text-lead">{{ $t('gifts_text') }}</p>
                    <div class="cta-row">
                        @if ($bundlesUrl)
                            <a href="{{ $bundlesUrl }}" data-magnet class="{{ $btnMain }}">{{ $t('gifts_bundles') }} <span aria-hidden="true">→</span></a>
                        @endif
                        @if ($giftsUrl = $link('gifts.index'))
                            <a href="{{ $giftsUrl }}" data-magnet class="{{ $btnGlass }}">{{ $t('gifts_finder') }}</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($services)
        <section class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] pt-20 sm:pt-[clamp(90px,14vh,150px)]" aria-labelledby="own-title">
            <div class="mb-10 flex flex-wrap items-end justify-between gap-x-10 gap-y-5">
                <div>
                    <p class="eyebrow mb-[22px]">{{ $t('own_eyebrow') }}</p>
                    <h2 id="own-title" class="{{ $sectionTitle }}">{{ $em('own_heading') }}</h2>
                </div>
                <p class="m-0 max-w-[40ch] text-[16.5px] text-lead">{{ $t('own_text') }}</p>
            </div>
            <div data-aura-rise class="flex flex-wrap gap-[clamp(16px,2vw,28px)]">
                @foreach ($services as [$url, $place, $alt, $material, $title, $text, $from])
                    @php($servicePhoto = $photo($place))
                    <a href="{{ $url }}" class="group relative block h-[clamp(460px,72vh,700px)] min-w-0 flex-[1_1_420px] overflow-hidden rounded-[24px] text-sand hover:text-sand focus-visible:outline-offset-4">
                        @if ($servicePhoto->exists())
                            <img src="{{ $servicePhoto->url }}" {{ $servicePhoto->srcsetAttributes('(min-width: 900px) 50vw, 100vw') }} alt="{{ $servicePhoto->alt($alt) }}" loading="lazy" width="900" height="1200" data-aura-parallax="6" style="{{ $servicePhoto->style() }}" class="absolute inset-x-0 -top-[8%] h-[116%] w-full object-cover">
                        @endif
                        <span class="absolute inset-0 bg-linear-to-b from-transparent from-40% to-ink/55"></span>
                        <span class="glass-dark absolute inset-x-3.5 bottom-3.5 z-2 block rounded-[18px] px-6 py-[22px] transition-transform duration-800 ease-clay group-hover:-translate-y-2">
                            <span class="text-[10.5px] tracking-[0.26em] text-on-dark uppercase">{{ $material }}</span>
                            <span class="mt-2 block font-serif text-[length:clamp(28px,3vw,40px)] leading-[1.05] font-light text-sand">{{ $title }}</span>
                            <span class="mt-2 block max-w-[42ch] text-[14.5px] text-on-dark">{{ $text }}</span>
                            <span class="mt-3.5 block text-[14px] text-rose">{{ $from ? $t('from_price', ['price' => Money::format((int) $from)]) : $t('how_it_works') }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($workshopsUrl && $workshops)
        <section class="focus-on-dark relative mt-20 overflow-hidden rounded-t-[36px] bg-ink py-20 sm:mt-[clamp(90px,14vh,150px)] sm:py-[clamp(100px,16vh,170px)] text-on-dark" aria-labelledby="workshops-title">
            <div aria-hidden="true" class="aura-orb aura-orb-1 opacity-100 [background:radial-gradient(circle,rgb(214_163_156/.45),rgb(214_163_156/0)_65%)]"></div>
            <div aria-hidden="true" class="aura-orb aura-orb-2 opacity-100 [background:radial-gradient(circle,rgb(185_142_100/.4),rgb(185_142_100/0)_65%)]"></div>
            <div class="relative mx-auto flex max-w-[1360px] flex-wrap items-center gap-[clamp(40px,6vw,80px)] px-[clamp(18px,4vw,48px)]">
                <div class="min-w-0 flex-[1_1_440px]">
                    <p class="eyebrow mb-[22px] text-label-dark [--eyebrow-dash:var(--color-rose)]">{{ $t('workshops_eyebrow') }}</p>
                    <h2 id="workshops-title" class="font-serif text-[length:clamp(44px,6.2vw,96px)] leading-[.95] font-light tracking-[-0.02em] text-sand">{{ $em('workshops_heading', 'text-rose italic whitespace-nowrap') }}</h2>
                    <p class="mt-6 max-w-[44ch] text-[17.5px] leading-[1.68] text-on-dark-muted">{{ $t('workshops_text') }}</p>
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
                                    <span class="font-serif text-[22px] font-light whitespace-nowrap text-sand">{{ $t('workshops_price', ['price' => Money::format((int) $workshop['price_gross']), 'unit' => $workshop['unit_label'] ?? '']) }}</span>
                                @endisset
                            </a>
                        @endforeach
                    </div>
                    <div class="mt-8">
                        <a href="{{ $workshopsUrl }}" data-magnet class="fill-btn inline-flex min-h-[52px] items-center gap-2.5 rounded-full bg-linen px-[30px] text-[14px] font-medium tracking-[0.04em] text-ink [--fill:var(--color-rose)] hover:bg-rose hover:text-ink">{{ $t('workshops_cta') }} <span aria-hidden="true">→</span></a>
                    </div>
                </div>
                <div aria-hidden="true" class="relative h-[clamp(460px,70vh,680px)] min-w-0 flex-[1_1_420px]">
                    @foreach ([['home-workshops-1', 'top-[6%] left-0 h-[70%] w-[58%]'], ['home-workshops-2', 'top-0 right-0 h-[44%] w-[38%]'], ['home-workshops-3', 'right-[6%] bottom-0 h-[46%] w-[46%]']] as [$place, $position])
                        @continue (! ($collage = $photo($place))->exists())
                        <div class="absolute overflow-hidden rounded-[20px] {{ $position }}"><img src="{{ $collage->url }}" {{ $collage->srcsetAttributes('(min-width: 900px) 30vw, 60vw') }} alt="" loading="lazy" width="800" height="1000" data-aura-parallax="{{ 6 + $loop->index * 4 }}" style="{{ $collage->style() }}" class="size-full object-cover"></div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Photos saved on the shop's own disk, so looking at them sends nothing to Instagram (privacy policy §9). --}}
    @if ($instagramPosts->isNotEmpty())
        <section class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] pt-20 sm:pt-[clamp(90px,14vh,150px)]" aria-labelledby="instagram-title">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-5">
                <div>
                    @if ($instagramHandle !== '')
                        <p class="eyebrow mb-[22px]">{{ '@'.$instagramHandle }}</p>
                    @endif
                    <h2 id="instagram-title" class="{{ $sectionTitle }}">{{ $t('instagram_heading') }} <span aria-hidden="true" class="text-brown">🪐</span></h2>
                </div>
                @if ($instagramHandle !== '')
                    <a href="https://www.instagram.com/{{ $instagramHandle }}/" target="_blank" rel="noopener" data-magnet class="{{ $btnGlass }}">{{ $t('instagram_follow') }}</a>
                @endif
            </div>
            <div class="glass rounded-[26px] p-3.5">
                <div class="mb-3.5 flex flex-wrap items-center justify-between gap-3 border-b border-line px-1.5 pt-1 pb-3.5">
                    <div class="flex items-center gap-2.5">
                        <span class="size-2 animate-ma-pulse rounded-full bg-rose"></span>
                        <span class="text-[11.5px] tracking-[0.14em] text-label uppercase">{{ $t('instagram_live') }}</span>
                    </div>
                    <span class="text-[12px] text-label">{{ $t('instagram_last', ['ago' => $instagramPosts->first()->posted_at->diffForHumans()]) }}</span>
                </div>
                {{-- Six photos always fill whole rows: two, three or six to a row. --}}
                <div data-aura-rise class="grid grid-cols-2 gap-2.5 min-[520px]:grid-cols-3 min-[980px]:grid-cols-6">
                    @foreach ($instagramPosts as $post)
                        <a href="{{ $post->permalink }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-[14px] bg-linen">
                            <img src="{{ $post->imageUrl() }}" alt="{{ $post->alt() }}" loading="lazy" width="1080" height="1080" style="object-position: {{ $post->objectPosition() }}" class="block aspect-square w-full object-cover transition-transform duration-[1.1s] ease-clay hover:scale-[1.06]">
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($cards)
        <section class="mx-auto max-w-[1360px] px-[clamp(18px,4vw,48px)] pt-20 sm:pt-[clamp(90px,14vh,150px)] pb-20 sm:pb-[clamp(90px,14vh,150px)]">
            <div data-aura-rise class="grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-[clamp(16px,2vw,28px)]">
                {{-- The first card stands out at rest; on hover every card fills with the same rose, text in ink and graphite. --}}
                @foreach ($cards as [$url, $dark, $eyebrow, $title, $text])
                    <a href="{{ $url }}" @class([
                        'group fill-btn flex flex-col justify-between gap-8 rounded-[24px] px-7 py-8 sm:min-h-[260px] sm:px-8 sm:py-[38px] transition-[background-color,border-color,color,transform] duration-500 ease-clay [--fill:var(--color-rose)] hover:-translate-y-1 hover:border-rose hover:bg-rose hover:text-ink',
                        'focus-on-dark bg-ink text-sand' => $dark,
                        'glass text-ink' => ! $dark,
                    ])>
                        <span @class(['text-[10.5px] tracking-[0.24em] uppercase transition-colors duration-500 group-hover:text-graphite', 'text-rose' => $dark, 'text-brown' => ! $dark])>{{ $eyebrow }}</span>
                        <span>
                            <span class="mb-2.5 block font-serif text-[31px] leading-[1.1] font-light">{{ $title }}</span>
                            <span @class(['block text-[14.5px] leading-[1.6] transition-colors duration-500 group-hover:text-graphite', 'text-on-dark' => $dark, 'text-muted' => ! $dark])>{{ $text }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-shared::layout>
