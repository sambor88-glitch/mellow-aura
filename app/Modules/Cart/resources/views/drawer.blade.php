@inject('cart', 'App\Modules\Cart\Cart')
{{-- A modal dialog: focus stays inside, and Esc, a click on the backdrop or × closes it. --}}
<dialog id="cart" data-count="{{ $cart->count() }}" x-data aria-labelledby="cart-title"
        x-on:click.self="$el.close()" x-on:keydown.escape="$el.close()" x-on:close="$store.cart.closed()"
        class="fixed inset-0 size-full max-h-none max-w-none animate-ma-in justify-end bg-scrim/44 text-ink open:flex backdrop:bg-transparent">
    <div class="flex h-full w-[min(430px,100%)] animate-ma-slide flex-col bg-cream shadow-drawer">
        <div class="flex items-center justify-between border-b border-divider px-[26px] py-6">
            <h2 id="cart-title" class="font-serif text-[23px]">Twój koszyk</h2>
            <button type="button" x-on:click="$el.closest('dialog').close()" data-focus="close" aria-label="Zamknij koszyk"
                    class="relative px-2 py-1 text-[22px] leading-none text-label after:absolute after:-inset-2 hover:text-ink">×</button>
        </div>
        <div id="cart-content" class="flex min-h-0 flex-1 flex-col">
            @include('cart::content')
        </div>
    </div>

    <div role="status" class="fixed bottom-[26px] left-1/2 w-max max-w-[calc(100%-32px)] -translate-x-1/2">
        <div x-cloak x-show="$store.cart.opened && $store.cart.notice" x-text="$store.cart.notice"
             class="animate-ma-up-quick rounded-full bg-ink px-6 py-3.5 text-center text-[14px] text-sand shadow-pill"></div>
    </div>
</dialog>

{{-- The same confirmation when the drawer is closed, e.g. an error after "Dodaj do koszyka". --}}
<div x-data role="status" class="fixed bottom-[26px] left-1/2 z-120 w-max max-w-[calc(100%-32px)] -translate-x-1/2">
    <div x-cloak x-show="! $store.cart.opened && $store.cart.notice" x-text="$store.cart.notice"
         class="animate-ma-up-quick rounded-full bg-ink px-6 py-3.5 text-center text-[14px] text-sand shadow-pill"></div>
</div>
