@use('App\Modules\Shared\Navigation')
@use('App\Modules\Shared\Support\Money')
@php
    $photos = $product->photos();
    $variantData = $product->variants->map(fn ($variant) => [
        'id' => $variant->id,
        'price' => $variant->price_gross,
        'old' => $variant->isDiscounted() ? $variant->compare_at_price : null,
        'lowest' => $variant->isDiscounted() ? $variant->lowestPriceBeforeDiscount() : null,
        'stock' => $variant->stock,
    ])->values();
    $available = $product->isAvailable();
    $customOrderUrl = Navigation::url('custom-orders.show');
    $cartUrl = Navigation::url('cart.store');
    $canBuy = $cartUrl !== null;
    $shippingText = collect([
        $dispatchTime ? $dispatchTime.' roboczych' : null,
        $freeShippingFrom ? 'gratis od '.Money::format($freeShippingFrom) : null,
    ])->filter()->implode(', ');
@endphp
<x-shared::layout :seo="$seo" :image="$product->coverUrl()">
    <div class="ma-product ma-view"
         x-data="productPage({{ Js::from(['photos' => $photos->map->getUrl()->values(), 'variants' => $variantData, 'selected' => $selected?->id]) }})"
         @keydown.escape.window="zoomed && closeZoom()">
        <x-shared::breadcrumbs :trail="$breadcrumbs" />

        <div class="ma-product__layout">
            <div class="ma-product__gallery">
                @if ($photos->isNotEmpty())
                    <button type="button" class="ma-product__photo" x-ref="photoButton" @click="openZoom()" aria-label="Powiększ zdjęcie: {{ $product->name }}">
                        <img src="{{ $photos->first()->getUrl() }}" :src="photos[photo]" alt="{{ $product->name }}" fetchpriority="high">
                        <span class="ma-product__zoom-hint" aria-hidden="true">Powiększ fakturę ⌕</span>
                    </button>
                    @if ($photos->count() > 1)
                        <div class="ma-thumbs">
                            @foreach ($photos as $thumb)
                                <button type="button" class="ma-thumb" @click="photo = {{ $loop->index }}"
                                        :aria-current="photo === {{ $loop->index }} ? 'true' : 'false'"
                                        aria-current="{{ $loop->first ? 'true' : 'false' }}"
                                        aria-label="Zdjęcie {{ $loop->iteration }} z {{ $photos->count() }}">
                                    <img src="{{ $thumb->getUrl() }}" alt="" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="ma-product__no-photo">Zdjęcie tej rzeczy jeszcze się robi.</div>
                @endif
            </div>

            <div class="ma-product__info">
                <div class="ma-product__overline">
                    {{ $product->category?->name }}@if ($product->is_one_off) &middot; unikat @endif
                </div>
                <h1 class="ma-h1 ma-h1--product">{{ $product->name }}</h1>

                @if ($selected)
                    <div class="ma-product__price" aria-live="polite">
                        <span class="ma-product__price-now" x-text="money(variant.price)">{{ Money::format($selected->price_gross) }}</span>
                        <template x-if="variant.old">
                            <span class="ma-product__price-old"><span class="ma-sr-only">Cena przed obniżką: </span><span x-text="money(variant.old)"></span></span>
                        </template>
                        <template x-if="variant.lowest">
                            <span class="ma-product__price-lowest">Najniższa cena z 30 dni przed obniżką: <span x-text="money(variant.lowest)"></span></span>
                        </template>
                        @if ($selected->isDiscounted())
                            <noscript>
                                <span class="ma-product__price-old">{{ Money::format($selected->compare_at_price) }}</span>
                                <span class="ma-product__price-lowest">Najniższa cena z 30 dni przed obniżką: {{ Money::format($selected->lowestPriceBeforeDiscount()) }}</span>
                            </noscript>
                        @endif
                    </div>
                @endif

                @if (filled($product->description))
                    <p class="ma-product__description">{{ $product->description }}</p>
                @endif

                @if ($dimensions = $product->dimensionList())
                    <ul class="ma-dims" aria-label="Wymiary">
                        @foreach ($dimensions as $dimension)
                            <li><span class="ma-dims__label">{{ $dimension['label'] }}</span><span>{{ $dimension['value'] }}</span></li>
                        @endforeach
                    </ul>
                @endif

                <form method="post" @if ($canBuy) action="{{ $cartUrl }}" @endif>
                    @csrf
                    <input type="hidden" name="quantity" :value="qty" value="1">

                    @if ($product->variants->count() > 1)
                        <fieldset class="ma-variants">
                            <legend>Rozmiar i cena</legend>
                            <div class="ma-variants__list">
                                @foreach ($product->variants as $variant)
                                    <label @class(['ma-variant', 'ma-variant--sold-out' => $variant->stock === 0])>
                                        <input type="radio" name="variant_id" value="{{ $variant->id }}"
                                               @checked($variant->is($selected)) @disabled($variant->stock === 0)
                                               @change="choose({{ $variant->id }})">
                                        <span class="ma-variant__label">{{ $variant->label }}</span>
                                        <span class="ma-variant__price">{{ $variant->stock === 0 ? 'wyprzedane' : Money::format($variant->price_gross) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @elseif ($selected)
                        <input type="hidden" name="variant_id" value="{{ $selected->id }}">
                    @endif

                    @if ($product->is_one_off)
                        <p class="ma-note">Każdy kubek jest jeden — po 1 sztuce z każdego napisu. Kolejny zrobię na zamówienie.</p>
                    @endif

                    @if ($available)
                        <template x-if="variant.stock === 1">
                            <p class="ma-note ma-note--warning">Ostatnia sztuka — kolejną zrobię na zamówienie</p>
                        </template>
                    @else
                        <p class="ma-note ma-note--warning">Ta sztuka już pojechała do kogoś — kolejną zrobię na zamówienie</p>
                    @endif

                    @if ($available && $canBuy)
                        <div class="ma-buy">
                            <div class="ma-qty" role="group" aria-label="Liczba sztuk">
                                <button type="button" @click="dec()" aria-label="Jedna sztuka mniej">−</button>
                                <output x-text="qty" aria-live="polite">1</output>
                                <button type="button" @click="inc()" :disabled="qty >= maxQty" aria-label="Jedna sztuka więcej">+</button>
                            </div>
                            <button type="submit" class="ma-btn ma-btn--primary">
                                Dodaj do koszyka &middot; <span x-text="money(total)">{{ $selected ? Money::format($selected->price_gross) : '' }}</span>
                            </button>
                        </div>
                        <div class="ma-blik">
                            <span class="ma-blik__mark">BLIK</span>
                            <span>zapłacisz w 10 sekund kodem z aplikacji banku</span>
                        </div>
                    @endif
                </form>

                <dl class="ma-facts">
                    @if ($shippingText !== '')
                        <div>
                            <dt>Wysyłka</dt>
                            <dd>{{ $shippingText }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt>Certyfikat unikatu</dt>
                        <dd>W paczce karta z numerem, datą wypału i podpisem</dd>
                    </div>
                    @if ($care = $product->careNote())
                        <div>
                            <dt>Pielęgnacja</dt>
                            <dd>{{ $care }}</dd>
                        </div>
                    @endif
                    @if ($customOrderUrl)
                        <div>
                            <dt>Chcesz inaczej?</dt>
                            <dd>Napis, rozmiar, szkliwo — wszystko robię na zamówienie. <a href="{{ $customOrderUrl }}">Napisz, co wymyśliłaś</a></dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="ma-related" aria-labelledby="z-tej-samej-polki">
                <h2 id="z-tej-samej-polki" class="ma-h2">Z tej samej półki</h2>
                <div class="ma-grid ma-grid--related">
                    @foreach ($related as $item)
                        <article>
                            <a href="{{ route('product.show', $item) }}" class="ma-related__media" tabindex="-1" aria-hidden="true">
                                @if ($cover = $item->coverUrl())
                                    <img src="{{ $cover }}" alt="{{ $item->name }}" loading="lazy">
                                @endif
                            </a>
                            <h3 class="ma-card__title"><a href="{{ route('product.show', $item) }}" class="ma-related__name">{{ $item->name }}</a></h3>
                            <x-catalog::price :product="$item" class="ma-related__price" />
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($photos->isNotEmpty())
            <div class="ma-zoom focus-on-dark" x-show="zoomed" x-cloak x-transition.opacity @click.self="closeZoom()"
                 role="dialog" aria-modal="true" aria-label="Zdjęcie w powiększeniu">
                <img :src="photos[photo]" alt="{{ $product->name }} — powiększenie" @click="closeZoom()">
                <button type="button" class="ma-zoom__close" x-ref="zoomClose" @click="closeZoom()" aria-label="Zamknij powiększenie">×</button>
            </div>
        @endif
    </div>
</x-shared::layout>
