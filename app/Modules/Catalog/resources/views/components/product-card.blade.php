@use('App\Modules\Catalog\Models\Product')
@use('App\Modules\Shared\Support\Money')
@props(['product', 'delay' => 0])
{{-- Product tile from MellowAura.dc.html ("Co teraz jest w pracowni"); reused by the shop list. --}}
@php
    $variant = $product->headlineVariant();
    $image = $product->getFirstMedia(Product::GALLERY);
    $lowest = $variant?->lowestPriceBeforeDiscount();
@endphp
<a href="{{ $product->url() }}" class="ma-product-card" style="animation-delay: {{ $delay }}s">
    <span class="ma-product-card__media">
        @if ($image)
            <img src="{{ $image->getUrl() }}" alt="{{ $product->name }}" loading="lazy">
        @endif
        @if ($variant?->isDiscounted())
            <span class="ma-product-card__badge">promocja</span>
        @endif
    </span>
    @if ($product->category)
        <span class="ma-product-card__category">{{ $product->category->name }}</span>
    @endif
    <span class="ma-product-card__name">{{ $product->name }}</span>
    @if ($variant)
        <span class="ma-product-card__price">
            <span>{{ $product->hasPriceRange() ? 'od ' : '' }}{{ Money::format($variant->price_gross) }}</span>
            @if ($variant->isDiscounted())
                <s>{{ Money::format($variant->compare_at_price) }}</s>
            @endif
        </span>
        @if ($lowest !== null)
            <span class="ma-product-card__lowest">Najniższa cena z 30 dni przed obniżką: {{ Money::format($lowest) }}</span>
        @endif
    @endif
</a>
