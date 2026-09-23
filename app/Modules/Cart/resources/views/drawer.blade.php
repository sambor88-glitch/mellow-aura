@inject('cart', 'App\Modules\Cart\Cart')
{{-- A modal dialog: focus stays inside, and Esc, a click on the backdrop or × closes it. --}}
<dialog id="cart" data-count="{{ $cart->count() }}" x-data aria-labelledby="cart-title"
        x-on:click.self="$el.close()" x-on:keydown.escape="$el.close()" x-on:close="$store.cart.closed()"
        class="fixed inset-0 size-full max-h-none max-w-none animate-ma-in justify-end bg-ink/28 p-2.5 text-ink backdrop-blur-[6px] open:flex backdrop:bg-transparent">
    {{-- A glass panel lifted off the edge that slides in out of a blur (mellowaura-design, „Szuflada koszyka”). --}}
    <div class="flex h-full w-[min(430px,100%)] animate-[auraDrawer_.65s_var(--ease-clay)_both] flex-col overflow-hidden rounded-[26px] border border-glass-line bg-cream/80 backdrop-blur-[30px] backdrop-saturate-[1.4]">
        <div class="flex items-center justify-between border-b border-line px-[26px] py-5">
            <h2 id="cart-title" class="font-serif text-[32px] leading-none font-light">{{ __('cart::drawer.title') }}</h2>
            <button type="button" x-on:click="$el.closest('dialog').close()" data-focus="close" aria-label="{{ __('cart::drawer.close') }}"
                    class="grid size-11 place-items-center rounded-full border border-line-strong text-[20px] leading-none text-ink transition-colors duration-300 hover:border-ink hover:bg-sand-dark">×</button>
        </div>
        <div id="cart-content" class="flex min-h-0 flex-1 flex-col">
            @include('cart::content')
        </div>
    </div>

    <div role="status" class="pointer-events-none fixed bottom-[26px] left-1/2 w-max max-w-[calc(100%-32px)] -translate-x-1/2">
        <div x-cloak x-show="$store.cart.opened && $store.cart.notice" x-text="$store.cart.notice"
             class="animate-ma-up-quick rounded-full bg-ink px-6 py-3.5 text-center text-[14px] text-sand shadow-pill"></div>
    </div>
</dialog>

{{-- The same confirmation when the drawer is closed, e.g. an error after "Dodaj do koszyka". --}}
<div x-data role="status" class="pointer-events-none fixed bottom-[26px] left-1/2 z-120 w-max max-w-[calc(100%-32px)] -translate-x-1/2">
    <div x-cloak x-show="! $store.cart.opened && $store.cart.notice" x-text="$store.cart.notice"
         class="animate-ma-up-quick rounded-full bg-ink px-6 py-3.5 text-center text-[14px] text-sand shadow-pill"></div>
</div>
