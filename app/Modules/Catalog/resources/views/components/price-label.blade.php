@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\Money')
@props(['product'])
{{-- In the currency of the page: the product was loaded with only the variants priced in it (Product::withShelf). --}}
@php($prices = $product->variants->map(fn ($variant) => $variant->price())->filter(fn ($price) => $price !== null))
@php($lowest = Money::format((int) $prices->min(), Locales::currency()))
{{ $prices->min() === $prices->max() ? $lowest : __('catalog::card.from', ['price' => $lowest]) }}
