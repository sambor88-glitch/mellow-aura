@use('App\Modules\Shared\Support\Money')
@inject('cart', 'App\Modules\Cart\Cart')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $lines = $cart->lines();
    $subtotal = $cart->subtotal();
    $freeFrom = (int) $settings->get('free_shipping_threshold', 0);
    $freeReached = $freeFrom > 0 && $subtotal >= $freeFrom;
    $cheapestShipping = collect((array) $settings->get('shipping_methods', []))->pluck('price_gross')->filter(fn ($price) => $price > 0)->min();
    // Vouchers sent as PDFs alone come by e-mail: no free-shipping threshold to reach and no delivery to pay for.
    $needsDelivery = $cart->needsDelivery();
@endphp
<div class="min-h-0 flex-1 overflow-y-auto px-[26px] py-[22px]">
    @forelse ($lines as $line)
        <div class="flex gap-3.5 border-b border-sand-dark py-4">
            @if ($thumbnail = $line->thumbnailUrl())
                <img src="{{ $thumbnail }}" alt="{{ $line->thumbnailAlt() }}" width="74" height="92" class="h-[92px] w-[74px] flex-none rounded-[3px] object-cover">
            @else
                <div class="h-[92px] w-[74px] flex-none rounded-[3px] bg-line-soft"></div>
            @endif
            <div class="min-w-0 flex-1">
                <div class="font-serif text-[17.5px] leading-[1.25]">{{ $line->name() }}</div>
                <div class="mt-[3px] mb-2.5 text-[12.5px] [overflow-wrap:anywhere] text-label">{{ $line->details() }}</div>
                <div class="flex items-center gap-3.5">
                    <div class="flex items-center rounded-full border border-line">
                        <form method="post" action="{{ route('cart.update', $line->key) }}" x-on:submit.prevent="$store.cart.send($el)">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="quantity" value="{{ $line->quantity - 1 }}">
                            <button data-focus="{{ $line->key }}-less" aria-label="Mniej: {{ $line->name() }}" class="relative size-7 text-[15px] text-muted after:absolute after:-inset-2">−</button>
                        </form>
                        <span class="min-w-[18px] text-center text-[13.5px]">{{ $line->quantity }}</span>
                        <form method="post" action="{{ route('cart.update', $line->key) }}" x-on:submit.prevent="$store.cart.send($el)">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="quantity" value="{{ $line->quantity + 1 }}">
                            <button data-focus="{{ $line->key }}-more" aria-label="Więcej: {{ $line->name() }}" class="relative size-7 text-[15px] text-muted after:absolute after:-inset-2">+</button>
                        </form>
                    </div>
                    <span class="text-[14px]">{{ Money::format($line->total()) }}</span>
                </div>
            </div>
            <form method="post" action="{{ route('cart.destroy', $line->key) }}" x-on:submit.prevent="$store.cart.send($el)" class="self-start">
                @csrf
                @method('DELETE')
                <button data-focus="{{ $line->key }}-remove" aria-label="Usuń z koszyka: {{ $line->name() }}" class="relative p-0.5 text-[12px] text-hint after:absolute after:-inset-3 hover:text-error">usuń</button>
            </form>
        </div>
    @empty
        <div class="px-3 py-14 text-center text-label">
            <div class="mb-2 font-serif text-[21px] text-ink">Jeszcze pusto</div>
            <p class="mb-[22px] text-[14.5px] leading-[1.6]">Ceramika czeka w pracowni. Każda sztuka jest jedna.</p>
            <a href="{{ route('shop.index') }}" class="inline-block rounded-full bg-ink px-[26px] py-[13px] text-[13.5px] text-linen hover:bg-navy hover:text-linen">Zobacz produkty</a>
        </div>
    @endforelse
</div>

@if ($lines->isNotEmpty())
    <div class="border-t border-divider bg-linen px-[26px] pt-[22px] pb-[26px]">
        @if ($needsDelivery && $freeReached)
            <div class="mb-3.5 flex items-center gap-[9px] rounded-[4px] border border-success-line bg-success-soft px-3.5 py-[11px] text-[13px] text-success-text">
                <span aria-hidden="true" class="inline-block animate-ma-check">✓</span><span>Wysyłka gratis — próg osiągnięty</span>
            </div>
        @elseif ($needsDelivery && $freeFrom > 0)
            <div class="mb-3.5">
                <div class="mb-[7px] text-[12.5px] text-muted">Do darmowej wysyłki brakuje {{ Money::format($freeFrom - $subtotal) }}</div>
                <div aria-hidden="true" class="h-[5px] overflow-hidden rounded-full bg-divider">
                    <div class="h-full rounded-full bg-dash transition-[width] duration-400" style="width: {{ min(100, (int) round($subtotal / $freeFrom * 100)) }}%"></div>
                </div>
            </div>
        @endif

        <div class="mb-2 flex justify-between text-[14.5px] text-muted"><span>Produkty</span><span>{{ Money::format($subtotal) }}</span></div>
        @if (! $needsDelivery)
            <div class="mb-3.5 flex justify-between text-[14.5px] text-muted"><span>Dostawa</span><span>mailem, gratis</span></div>
        @elseif ($freeReached || $cheapestShipping !== null)
            <div class="mb-3.5 flex justify-between text-[14.5px] text-muted">
                <span>Dostawa</span>
                <span>{{ $freeReached ? 'gratis od '.Money::format($freeFrom) : 'od '.Money::format((int) $cheapestShipping) }}</span>
            </div>
        @endif
        <div class="mb-[18px] flex justify-between border-t border-divider pt-3.5 font-serif text-[22px]"><span>Razem</span><span>{{ Money::format($subtotal) }}</span></div>

        @if (Route::has('checkout.index'))
            <a href="{{ route('checkout.index') }}" class="block rounded-full bg-ink p-4 text-center text-[14.5px] tracking-[0.03em] text-linen transition duration-300 hover:bg-navy hover:text-linen active:scale-[.97]">Przejdź do zamówienia</a>
        @endif
        <div class="mt-3.5 flex items-center justify-center gap-2.5 text-[11.5px] tracking-[0.1em] text-label uppercase">
            <span class="rounded-[3px] bg-navy px-[7px] py-[3px] text-[10px] font-semibold tracking-[0.08em] text-white">BLIK</span>
            <span>Przelewy24</span><span>&middot;</span><span>karta</span>
        </div>
    </div>
@endif
