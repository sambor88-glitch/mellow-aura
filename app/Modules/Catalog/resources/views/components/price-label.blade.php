@use('App\Modules\Shared\Support\Money')
@props(['product'])
@php($prices = $product->variants->pluck('price_gross'))
{{ $prices->min() === $prices->max() ? Money::format($prices->min()) : 'od '.Money::format($prices->min()) }}
