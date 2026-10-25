@inject('cart', 'App\Modules\Cart\Cart')
<button type="button" x-data x-on:click="$store.cart.open($el)" aria-haspopup="dialog" aria-controls="cart"
        class="fill-btn ml-auto flex min-h-11 items-center gap-2 rounded-full bg-ink px-3.5 py-[11px] min-[480px]:gap-2.5 min-[480px]:px-5 text-[13px] tracking-[0.04em] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">
    {{-- Below 480 px the pill has no room for the word: a bag shows it, and a screen reader still hears it. --}}
    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px] fill-none stroke-current min-[480px]:hidden" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8h14l-1.2 11.1a1 1 0 0 1-1 .9H7.2a1 1 0 0 1-1-.9L5 8Z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/></svg>
    <span class="max-[479px]:sr-only">{{ __('cart::drawer.button') }}</span>
    <span aria-live="polite"
          x-init="$watch('$store.cart.count', () => { $el.classList.remove('animate-ma-bump'); void $el.offsetWidth; $el.classList.add('animate-ma-bump') })"
          class="grid h-[21px] min-w-[21px] place-items-center rounded-full bg-rose px-[5px] text-[11.5px] font-semibold text-ink">
        <span class="sr-only">{{ __('cart::drawer.count') }} </span><span x-text="$store.cart.count">{{ $cart->count() }}</span>
    </span>
</button>
