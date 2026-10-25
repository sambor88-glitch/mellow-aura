@use('App\Modules\Catalog\Models\Product')
@use('App\Modules\Shared\Support\Money')
@use('Illuminate\Support\Facades\Vite')
{{-- Home view from MellowAura.dc.html (isHome). Reviews, workshop teasers and the Instagram
     feed join once their modules exist: no invented opinions, prices only from the panel. --}}
@php
    $photo = fn (string $file) => Vite::asset('zdjecia/'.$file);
    $heroVariant = $hero?->headlineVariant();
    $heroImage = $hero?->getFirstMedia(Product::GALLERY);
    $marqueeWords = ['glina', 'jedwab', 'len', 'złoto 24k', 'less waste', 'unikat', 'kraków'];
    $marqueePhotos = [
        ['kasia-talerz-zloto.webp', '1/1'], ['misa-laguna.webp', '3/4'], ['kubek-nie-powinnam.webp', '1/1'],
        ['wazon-rzezbiony.webp', '1/1'], ['podstawki-muszle-roz.webp', '3/4'], ['talerz-niebieski-odcisk.webp', '1/1'],
        ['patera-czarna-kolce.webp', '1/1'], ['zestaw-flatlay.webp', '3/4'],
    ];
@endphp
<x-shared::layout :seo="$seo">
    <div class="ma-home">
        <div class="ma-container">
            <section class="ma-hero">
                <div class="ma-hero__text">
                    <div class="ma-eyebrow"><span class="ma-eyebrow__line"></span><span>home studio &middot; kraków</span></div>
                    <h1 class="ma-hero__title">Glina i jedwab<br>z <em>jednej pary</em> rąk.</h1>
                    <p class="ma-lead">Ceramika formowana w dłoniach i jedwabne dodatki szyte z tkanin z drugiego obiegu — dwie dziedziny w jednej krakowskiej pracowni. Do tego kubki z napisem wbijanym stemplem, którego nie zetrze żadna zmywarka.</p>
                    <div class="ma-hero__actions">
                        <a href="{{ url('/kubek-z-napisem') }}" class="ma-button">Zaprojektuj kubek z napisem</a>
                        <a href="{{ url('/sklep') }}" class="ma-button ma-button--outline">Zobacz produkty</a>
                        <a href="{{ url('/warsztaty-ceramiczne-krakow') }}" class="ma-button ma-button--outline">Warsztaty</a>
                    </div>
                    <dl class="ma-stats">
                        <div><dt>ręczna robota</dt><dd>100%</dd></div>
                        <div><dt>płatność w 10 sekund</dt><dd>BLIK</dd></div>
                        @if ($dispatchTime)
                            <div><dt>wysyłka zamówienia</dt><dd>{{ $dispatchTime }}</dd></div>
                        @endif
                    </dl>
                </div>
                <div class="ma-hero__media">
                    @if ($hero)
                        <a href="{{ $hero->url() }}" class="ma-hero__circle">
                            <img src="{{ $heroImage?->getUrl() ?: $photo('wazon-rzezbiony.webp') }}" alt="Ręcznie formowana ceramika z pracowni MellowAura">
                        </a>
                        <a href="{{ $hero->url() }}" class="ma-hero__tag">
                            @if ($heroBadge)
                                <span class="ma-hero__badge">{{ $heroBadge }}</span>
                            @endif
                            <span class="ma-hero__name">{{ $hero->name }}</span>
                            @if ($heroVariant)
                                <span class="ma-hero__price">{{ $hero->hasPriceRange() ? 'od ' : '' }}{{ Money::format($heroVariant->price_gross) }}</span>
                            @endif
                        </a>
                    @else
                        <div class="ma-hero__circle">
                            <img src="{{ $photo('wazon-rzezbiony.webp') }}" alt="Ręcznie formowana ceramika z pracowni MellowAura">
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <div class="ma-marquee ma-marquee--words" aria-hidden="true">
            <div class="ma-marquee__track">
                @foreach ([1, 2] as $copy)
                    @foreach ($marqueeWords as $word)
                        <span>{{ $word }}</span><span class="ma-marquee__star">✦</span>
                    @endforeach
                @endforeach
            </div>
        </div>

        @if ($featured->isNotEmpty())
            <section class="ma-container ma-section ma-reveal">
                <div class="ma-section__head">
                    <h2 class="ma-section__title">Co teraz jest w pracowni</h2>
                    <a href="{{ url('/sklep') }}" class="ma-more">Wszystkie produkty →</a>
                </div>
                <div class="ma-product-grid">
                    @foreach ($featured as $product)
                        <x-catalog::product-card :product="$product" :delay="$loop->index * 0.09" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($kasiaHeading || $kasiaBody)
            <section class="ma-container ma-section ma-reveal">
                <div class="ma-split-card">
                    <div class="ma-split-card__media">
                        <img src="{{ $photo('kasia-talerz-zloto.webp') }}" alt="Kasia z talerzem zdobionym złotem" loading="lazy">
                    </div>
                    <div class="ma-split-card__body">
                        <div class="ma-eyebrow ma-eyebrow--plain">Cześć, jestem Kasia</div>
                        @if ($kasiaHeading)
                            <h2 class="ma-split-card__title">{{ $kasiaHeading }}</h2>
                        @endif
                        @if ($kasiaBody)
                            <p>{{ $kasiaBody }}</p>
                        @endif
                        <a href="{{ url('/o-mnie') }}" class="ma-more">Poznaj mnie bliżej →</a>
                    </div>
                </div>
            </section>
        @endif

        <section class="ma-container ma-section ma-reveal">
            <div class="ma-bundle-card">
                <div class="ma-bundle-card__media">
                    <img src="{{ $photo('kubek-cappuccino.webp') }}" alt="Kubek malowany ręcznie" loading="lazy">
                    <img src="{{ $photo('scrunchies-fiolet.webp') }}" alt="Jedwabne scrunchies" loading="lazy">
                </div>
                <div class="ma-bundle-card__body">
                    <div class="ma-eyebrow ma-eyebrow--plain">zestawy prezentowe</div>
                    <h2 class="ma-bundle-card__title">Kubek i scrunchie<br>w jednym odcieniu</h2>
                    <p>Dobieram szkliwo do tkaniny, nie odwrotnie. Zestawu, w którym glina i jedwab mają ten sam kolor, nie kupisz w pracowni, która robi tylko jedno z dwóch.</p>
                    <div class="ma-hero__actions">
                        <a href="{{ url('/zestawy-prezentowe') }}" class="ma-button">Zobacz zestawy</a>
                        <a href="{{ url('/prezenty') }}" class="ma-button ma-button--outline">Szukam prezentu</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="ma-container ma-section ma-reveal">
            <div class="ma-eyebrow ma-eyebrow--plain">z Twojego</div>
            <h2 class="ma-section__title">Przyślij mi coś swojego</h2>
            <p class="ma-section__intro">Dwie usługi, których nie zamówisz w żadnej innej pracowni — bo wymagają i gliny, i igły.</p>
            <div class="ma-service-grid">
                <a href="{{ url('/z-twojej-apaszki') }}" class="ma-service-card">
                    <img src="{{ $photo('opaska-jedwab.webp') }}" alt="Opaska z jedwabnej apaszki" loading="lazy">
                    <span class="ma-service-card__body">
                        <span class="ma-service-card__title">Z Twojej apaszki</span>
                        <span class="ma-service-card__text">Apaszka po babci wraca jako opaska i dwie scrunchies. Resztki oddaję.</span>
                        <span class="ma-service-card__price">@if ($scarfPriceFrom)od {{ Money::format((int) $scarfPriceFrom) }} @endif→</span>
                    </span>
                </a>
                <a href="{{ url('/odcisk-twojej-rosliny') }}" class="ma-service-card">
                    <img src="{{ $photo('talerz-niebieski-odcisk.webp') }}" alt="Talerz z odciskiem rośliny" loading="lazy">
                    <span class="ma-service-card__body">
                        <span class="ma-service-card__title">Odcisk Twojej rośliny</span>
                        <span class="ma-service-card__text">Kwiat z bukietu ślubnego zostaje w glinie. Roślina znika, rysunek zostaje.</span>
                        <span class="ma-service-card__price">@if ($imprintPriceFrom)od {{ Money::format((int) $imprintPriceFrom) }} @endif→</span>
                    </span>
                </a>
            </div>
        </section>

        <div class="ma-marquee ma-marquee--photos" aria-hidden="true">
            <div class="ma-marquee__track">
                @foreach ([1, 2] as $copy)
                    @foreach ($marqueePhotos as [$file, $ratio])
                        <img src="{{ $photo($file) }}" alt="" loading="lazy" style="aspect-ratio: {{ $ratio }}">
                    @endforeach
                @endforeach
            </div>
        </div>

        <section class="ma-container ma-section ma-section--last ma-reveal">
            <div class="ma-offer-grid">
                <a href="{{ url('/zamowienia-indywidualne') }}" class="ma-offer-card ma-offer-card--dark focus-on-dark">
                    <span class="ma-offer-card__label">na zamówienie</span>
                    <span>
                        <span class="ma-offer-card__title">Zaprojektujmy to razem</span>
                        <span class="ma-offer-card__text">Serwis na prezent, chrzciny, wesele albo jedna rzecz, której nigdzie nie ma.</span>
                    </span>
                </a>
                <a href="{{ url('/ceramika-dla-gastronomii') }}" class="ma-offer-card">
                    <span class="ma-offer-card__label">dla lokali</span>
                    <span>
                        <span class="ma-offer-card__title">Ceramika dla kawiarni i restauracji</span>
                        <span class="ma-offer-card__text">Powtarzalne formy, które wytrzymują zmywarkę i codzienny serwis.</span>
                    </span>
                </a>
                <a href="{{ url('/wypal-ceramiki-krakow') }}" class="ma-offer-card">
                    <span class="ma-offer-card__label">zaplecze</span>
                    <span>
                        <span class="ma-offer-card__title">Wypalę Twoje prace</span>
                        <span class="ma-offer-card__text">Miejsce w piecu dla osób bez własnego zaplecza — biskwit i wypał na ostro.</span>
                    </span>
                </a>
            </div>
        </section>
    </div>
</x-shared::layout>
