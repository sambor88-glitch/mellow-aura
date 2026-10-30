@use('App\Modules\Shared\Support\Money')
@props(['was' => null, 'lowest' => null, 'currency' => 'PLN'])
{{-- Only with the lowest price from the 30 days before the reduction may a price be crossed out (price information act, art. 4). --}}
@if ($was !== null && $lowest !== null)
    <span {{ $attributes->class(['block text-[12.5px] leading-[1.45] text-hint']) }}>
        <span class="sr-only">{{ __('shared::price.before') }} </span><span class="line-through">{{ Money::format($was, $currency) }}</span>
        <span class="block">{{ __('shared::price.lowest', ['price' => Money::format($lowest, $currency)]) }}</span>
    </span>
@endif
