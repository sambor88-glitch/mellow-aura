@use('App\Modules\Shared\Support\Money')
@props(['was' => null, 'lowest' => null])
{{-- Only with the lowest price from the 30 days before the reduction may a price be crossed out (price information act, art. 4). --}}
@if ($was !== null && $lowest !== null)
    <span {{ $attributes->class(['block text-[12.5px] leading-[1.45] text-hint']) }}>
        <span class="sr-only">Cena przed obniżką: </span><span class="line-through">{{ Money::format($was) }}</span>
        <span class="block">Najniższa cena z 30 dni przed obniżką: {{ Money::format($lowest) }}</span>
    </span>
@endif
