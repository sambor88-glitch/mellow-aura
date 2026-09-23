@props(['product'])
{{-- Hidden until Alpine starts: without JavaScript a heart could not save anything. --}}
<button type="button" x-data x-cloak
        x-on:click.prevent="$store.favorites.toggle({{ $product->id }})"
        x-bind:aria-pressed="$store.favorites.has({{ $product->id }}).toString()"
        x-bind:aria-label="($store.favorites.has({{ $product->id }}) ? @js(__('catalog::card.favorite_remove').' ') : @js(__('catalog::card.favorite_add').' ')) + @js($product->name)"
        {{ $attributes->class(['grid place-items-center p-0 leading-none text-ink transition duration-250 active:scale-[.92]']) }}>
    <span aria-hidden="true" x-text="$store.favorites.has({{ $product->id }}) ? '♥' : '♡'">♡</span>
</button>
