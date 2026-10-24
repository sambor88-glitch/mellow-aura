@use('App\Modules\Shared\Support\Money')
@props(['product'])
{{-- Card price: a crossed-out price always comes with the lowest price from the 30 days before the discount. --}}
@php($discounted = $product->discountedVariant())
<div {{ $attributes->class('ma-price') }}>
    @if ($discounted)
        <span>{{ Money::format($discounted->price_gross) }}</span>
        <span class="ma-price__old"><span class="ma-sr-only">Cena przed obniżką: </span>{{ Money::format($discounted->compare_at_price) }}</span>
        <span class="ma-price__lowest">Najniższa cena z 30 dni przed obniżką: {{ Money::format($discounted->lowestPriceBeforeDiscount()) }}</span>
    @elseif ($product->hasSinglePrice())
        <span>{{ Money::format($product->priceFrom()) }}</span>
    @else
        <span>od {{ Money::format($product->priceFrom()) }}</span>
    @endif
</div>
