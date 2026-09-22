@inject('cart', 'App\Modules\Cart\Cart')
<button type="button" x-data x-on:click="$store.cart.open($el)" aria-haspopup="dialog" aria-controls="cart"
        class="fill-btn ml-auto flex min-h-11 items-center gap-2.5 rounded-full bg-ink px-5 py-[11px] text-[13px] tracking-[0.04em] text-linen transition duration-300 hover:bg-navy active:scale-[.97]">
    <span>Koszyk</span>
    <span aria-live="polite"
          x-init="$watch('$store.cart.count', () => { $el.classList.remove('animate-ma-bump'); void $el.offsetWidth; $el.classList.add('animate-ma-bump') })"
          class="grid h-[21px] min-w-[21px] place-items-center rounded-full bg-rose px-[5px] text-[11.5px] font-semibold text-ink">
        <span class="sr-only">sztuk: </span><span x-text="$store.cart.count">{{ $cart->count() }}</span>
    </span>
</button>
