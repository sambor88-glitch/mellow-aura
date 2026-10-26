@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\Money')
@inject('cart', 'App\Modules\Cart\Cart')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    // Every line here is priced in the currency of the page (Cart::lines).
    $currency = Locales::currency();
    $money = fn (int $amount) => Money::format($amount, $currency);
    // Delivery, its free threshold and BLIK are Polish and in złoty; a euro basket learns its delivery at checkout.
    $inZloty = $currency === Locales::defaultCurrency();
    $lines = $cart->lines();
    $subtotal = $cart->subtotal();
    $freeFrom = $inZloty ? (int) $settings->get('free_shipping_threshold', 0) : 0;
    $freeReached = $freeFrom > 0 && $subtotal >= $freeFrom;
    $cheapestShipping = $inZloty ? collect((array) $settings->get('shipping_methods', []))->pluck('price_gross')->filter(fn ($price) => $price > 0)->min() : null;
    // Vouchers sent as PDFs alone come by e-mail: no free-shipping threshold to reach and no delivery to pay for.
    $needsDelivery = $cart->needsDelivery();
@endphp
<div class="min-h-0 flex-1 overflow-y-auto px-[26px] py-[22px]">
    @forelse ($lines as $line)
        <div class="flex animate-[auraItemIn_.6s_var(--ease-clay)_both] gap-3.5 border-b border-line py-4">
            @if ($thumbnail = $line->thumbnailUrl())
                <img src="{{ $thumbnail }}" alt="{{ $line->thumbnailAlt() }}" width="74" height="92" class="h-[92px] w-[74px] flex-none rounded-[12px] object-cover">
            @else
                <div class="h-[92px] w-[74px] flex-none rounded-[12px] bg-linen"></div>
            @endif
            <div class="min-w-0 flex-1">
                <div class="font-serif text-[19px] leading-[1.2] font-light">{{ $line->name() }}</div>
                <div class="mt-[3px] mb-2.5 text-[12.5px] [overflow-wrap:anywhere] text-label">{{ $line->details() }}</div>
                <div class="flex items-center gap-3.5">
                    <div class="glass flex items-center rounded-full">
                        <form method="post" action="{{ route('cart.update', $line->key) }}" x-on:submit.prevent="$store.cart.send($el)">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="quantity" value="{{ $line->quantity - 1 }}">
                            <button data-focus="{{ $line->key }}-less" aria-label="{{ __('cart::content.fewer', ['name' => $line->name()]) }}" class="relative size-7 text-[15px] text-muted after:absolute after:-inset-2">−</button>
                        </form>
                        <span class="min-w-[18px] text-center text-[13.5px]">{{ $line->quantity }}</span>
                        <form method="post" action="{{ route('cart.update', $line->key) }}" x-on:submit.prevent="$store.cart.send($el)">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="quantity" value="{{ $line->quantity + 1 }}">
                            <button data-focus="{{ $line->key }}-more" aria-label="{{ __('cart::content.more', ['name' => $line->name()]) }}" class="relative size-7 text-[15px] text-muted after:absolute after:-inset-2">+</button>
                        </form>
                    </div>
                    <span class="text-[14px]">{{ $money($line->total()) }}</span>
                </div>
            </div>
            <form method="post" action="{{ route('cart.destroy', $line->key) }}" x-on:submit.prevent="$store.cart.send($el)" class="self-start">
                @csrf
                @method('DELETE')
                <button data-focus="{{ $line->key }}-remove" aria-label="{{ __('cart::content.remove_label', ['name' => $line->name()]) }}" class="relative p-0.5 text-[12px] text-hint after:absolute after:-inset-3 hover:text-error">{{ __('cart::content.remove') }}</button>
            </form>
        </div>
    @empty
        <div class="px-3 py-14 text-center text-label">
            <div class="mb-2 font-serif text-[28px] font-light text-ink">{{ __('cart::content.empty_title') }}</div>
            <p class="mb-[22px] text-[14.5px] leading-[1.6]">{{ __('cart::content.empty_text') }}</p>
            <a href="{{ route('shop.index') }}" class="fill-btn inline-flex min-h-12 items-center rounded-full bg-ink px-[26px] text-[13.5px] text-linen [--fill:var(--color-rose)] hover:bg-rose hover:text-ink">{{ __('cart::content.empty_cta') }}</a>
        </div>
    @endforelse
</div>

@if ($lines->isNotEmpty())
    <div class="border-t border-line bg-linen/60 px-[26px] pt-[22px] pb-[26px]">
        @if ($needsDelivery && $freeReached)
            <div class="mb-3.5 flex items-center gap-[9px] rounded-[16px] border border-success-line bg-success-soft px-3.5 py-[11px] text-[13px] text-success-text">
                <span aria-hidden="true" class="inline-block animate-ma-check">✓</span><span>{{ __('cart::content.free_reached') }}</span>
            </div>
        @elseif ($needsDelivery && $freeFrom > 0)
            <div class="mb-3.5">
                <div class="mb-[7px] text-[12.5px] text-muted">{{ __('cart::content.free_missing', ['amount' => $money($freeFrom - $subtotal)]) }}</div>
                <div aria-hidden="true" class="h-[5px] overflow-hidden rounded-full bg-divider">
                    <div class="h-full rounded-full bg-linear-to-r from-rose to-brown transition-[width] duration-1000 ease-clay" style="width: {{ min(100, (int) round($subtotal / $freeFrom * 100)) }}%"></div>
                </div>
            </div>
        @endif

        <div class="mb-2 flex justify-between text-[14.5px] text-muted"><span>{{ __('cart::content.items') }}</span><span>{{ $money($subtotal) }}</span></div>
        @if (! $needsDelivery)
            <div class="mb-3.5 flex justify-between text-[14.5px] text-muted"><span>{{ __('cart::content.delivery') }}</span><span>{{ __('cart::content.delivery_email') }}</span></div>
        @elseif ($freeReached || $cheapestShipping !== null)
            <div class="mb-3.5 flex justify-between text-[14.5px] text-muted">
                <span>{{ __('cart::content.delivery') }}</span>
                <span>{{ $freeReached ? __('cart::content.delivery_free_from', ['price' => $money($freeFrom)]) : __('cart::content.delivery_from', ['price' => $money((int) $cheapestShipping)]) }}</span>
            </div>
        @elseif (! $inZloty)
            <div class="mb-3.5 flex justify-between text-[14.5px] text-muted"><span>{{ __('cart::content.delivery') }}</span><span>{{ __('cart::content.delivery_at_checkout') }}</span></div>
        @endif
        <div class="mb-[18px] flex items-baseline justify-between border-t border-line pt-3.5"><span class="text-[14.5px]">{{ __('cart::content.total') }}</span><span class="font-serif text-[32px] leading-none font-light tabular-nums">{{ $money($subtotal) }}</span></div>

        {{-- The checkout in the language of the page, or none: a euro basket never lands on a złoty checkout. --}}
        @if (Locales::has('checkout.index', Locales::current()))
            <a href="{{ route('checkout.index') }}" class="fill-btn block rounded-full bg-ink p-4 text-center text-[14.5px] font-medium tracking-[0.03em] text-linen transition duration-300 [--fill:var(--color-rose)] hover:bg-rose hover:text-ink active:scale-[.97]">{{ __('cart::content.checkout') }} <span aria-hidden="true">→</span></a>
        @endif
        @if ($inZloty)
            <div class="mt-3.5 flex items-center justify-center gap-2.5 text-[11.5px] tracking-[0.1em] text-label uppercase">
                <span class="rounded-full bg-navy px-2.5 py-[3px] text-[10px] font-semibold tracking-[0.08em] text-white">BLIK</span>
                <span>Przelewy24</span><span>&middot;</span><span>karta</span>
            </div>
        @endif
    </div>
@endif
