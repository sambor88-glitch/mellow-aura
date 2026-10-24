@use('App\Modules\Shared\Navigation')
@use('App\Modules\Shared\Support\Money')
@php
    $mugUrl = Navigation::url('mug.show');
    $workshopsUrl = Navigation::url('workshops.index');
    $aboutUrl = Navigation::url('about.show');
    $marquee = ['glina', 'jedwab', 'len', 'złoto 24k', 'less waste', 'unikat', 'kraków'];
@endphp
<x-shared::layout :seo="$seo" :image="$hero?->coverUrl()">
    <div class="ma-view">
        <div class="ma-container">
            <div class="ma-split">
                <div class="ma-hero__text">
                    <x-shared::eyebrow>home studio &middot; kraków</x-shared::eyebrow>
                    <h1 class="ma-h1">Glina i jedwab<br>z <em>jednej pary</em> rąk.</h1>
                    <p class="ma-lead">Ceramika formowana w dłoniach i jedwabne dodatki szyte z tkanin z drugiego obiegu — dwie dziedziny w jednej krakowskiej pracowni. Do tego kubki z napisem wbijanym stemplem, którego nie zetrze żadna zmywarka.</p>
                    <div class="ma-btn-row">
                        @if ($mugUrl)
                            <a href="{{ $mugUrl }}" class="ma-btn ma-btn--primary">Zaprojektuj kubek z napisem</a>
                        @endif
                        <a href="{{ route('shop.index') }}" @class(['ma-btn', 'ma-btn--secondary' => $mugUrl, 'ma-btn--primary' => ! $mugUrl])>Zobacz produkty</a>
                        @if ($workshopsUrl)
                            <a href="{{ $workshopsUrl }}" class="ma-btn ma-btn--secondary">Warsztaty</a>
                        @endif
                    </div>
                    <dl class="ma-stats">
                        <div><dt>ręczna robota</dt><dd>100%</dd></div>
                        <div><dt>płatność w 10 sekund</dt><dd>BLIK</dd></div>
                        @if ($dispatchTime)
                            <div><dt>wysyłka zamówienia</dt><dd>{{ $dispatchTime }}</dd></div>
                        @endif
                    </dl>
                </div>
                @if ($hero && ($heroImage = $hero->coverUrl()))
                    <div class="ma-hero__media">
                        <a href="{{ route('product.show', $hero) }}" class="ma-hero__blob" tabindex="-1" aria-hidden="true">
                            <img src="{{ $heroImage }}" alt="Ręcznie formowana ceramika z pracowni MellowAura" fetchpriority="high">
                        </a>
                        <a href="{{ route('product.show', $hero) }}" class="ma-hero__tag">
                            @if ($heroBadge)
                                <span class="ma-hero__badge">{{ $heroBadge }}</span>
                            @endif
                            <span class="ma-hero__name">{{ $hero->name }}</span>
                            <span class="ma-hero__price">{{ $hero->hasSinglePrice() ? '' : 'od ' }}{{ Money::format($hero->priceFrom()) }}</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div class="ma-marquee">
            <div class="ma-marquee__track" aria-hidden="true">
                @foreach ([1, 2] as $copy)
                    @foreach ($marquee as $word)
                        <span>{{ $word }}</span><span class="ma-marquee__star">✦</span>
                    @endforeach
                @endforeach
            </div>
        </div>

        @if ($featured->isNotEmpty())
            <section class="ma-section ma-reveal" aria-labelledby="w-pracowni">
                <div class="ma-section__head">
                    <h2 id="w-pracowni" class="ma-h2">Co teraz jest w pracowni</h2>
                    <a href="{{ route('shop.index') }}" class="ma-link-arrow">Wszystkie produkty →</a>
                </div>
                <div class="ma-grid">
                    @foreach ($featured as $product)
                        <x-catalog::product-card :product="$product" :delay="$loop->index * 0.09" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($kasiaHeading || $kasiaLead)
            <section class="ma-section ma-reveal" aria-labelledby="kasia">
                <div class="ma-intro-card">
                    <div class="ma-intro-card__photo">
                        <img src="{{ asset('zdjecia/kasia-talerz-zloto.webp') }}" alt="Kasia z talerzem zdobionym złotem" loading="lazy">
                    </div>
                    <div class="ma-intro-card__body">
                        <div class="ma-overline">Cześć, jestem Kasia</div>
                        @if ($kasiaHeading)
                            <h2 id="kasia" class="ma-h2">{{ $kasiaHeading }}</h2>
                        @endif
                        @if ($kasiaLead)
                            <p>{{ $kasiaLead }}</p>
                        @endif
                        @if ($aboutUrl)
                            <a href="{{ $aboutUrl }}" class="ma-link-arrow">Poznaj mnie bliżej →</a>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- Bundles, the two services, workshops, Instagram and the three dark cards
             come back with their modules: a section never links to a page that does not exist. --}}

        <div class="ma-photo-strip" aria-hidden="true">
            <div class="ma-photo-strip__track">
                @foreach ([1, 2] as $copy)
                    @foreach ($stripPhotos as [$file, $ratio])
                        <img src="{{ asset('zdjecia/'.$file) }}" alt="" loading="lazy" style="aspect-ratio: {{ $ratio }}">
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
</x-shared::layout>
