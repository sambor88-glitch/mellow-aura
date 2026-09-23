@use('App\Modules\Checkout\Enums\PaymentMethod')
@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\Money')
@php
    // Every amount here is in the currency of the basket.
    $money = fn (int $amount) => Money::format($amount, Locales::currency());
    $t = fn (string $key, array $replace = []) => __('checkout::checkout.'.$key, $replace);
    // A sentence with markup in it: the sentence is escaped, the markup put in place of its placeholder.
    $withLink = fn (string $sentence, string $placeholder, string $html) => str_replace($placeholder, $html, e($sentence));
    // Vouchers sent as PDFs alone need no address or note for the parcel, only an invoice may.
    $more = $needsDelivery ? ['street', 'postal_code', 'city', 'invoice_nip', 'note'] : ['invoice_nip'];
    $moreOpen = $errors->hasAny($more) || collect($more)->contains(fn (string $field) => filled(old($field)));
    $radio = 'group flex cursor-pointer items-center gap-3.5 rounded-[18px] border border-line bg-cream/60 px-[18px] transition-[border-color,background-color,transform] duration-300 hover:-translate-y-0.5 hover:border-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
@endphp

<x-shared::layout :title="$t('title')" :noindex="true">
    @if ($config['stripeKey'])
        {{-- Only here, only when the shop can really charge: the payment page is the one place that needs it. --}}
        <x-slot:head>
            <script src="https://js.stripe.com/v3/"></script>
        </x-slot:head>
    @endif
    @if ($analytics)
        <x-consent::analytics-event name="begin_checkout" :params="$analytics" />
    @endif
    <div class="mx-auto max-w-[1180px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-12 pb-24">
        @if ($lines->isEmpty())
            <div class="py-16 text-center">
                <h1 class="mb-4 font-serif text-[length:clamp(44px,6vw,88px)] leading-[.96] font-light tracking-[-0.02em]">{{ $t('empty_title') }}</h1>
                <p class="mx-auto mb-8 max-w-[46ch] text-[17px] leading-[1.7] text-lead">{{ $t('empty_text') }}</p>
                <a href="{{ route('shop.index') }}" class="fill-btn inline-flex min-h-[52px] items-center rounded-full bg-ink px-[30px] text-[14px] text-linen [--fill:var(--color-rose)] hover:bg-rose hover:text-ink">{{ $t('empty_cta') }}</a>
            </div>
        @else
            <p class="eyebrow mb-[18px]">{!! $t('eyebrow') !!}</p>
            <h1 class="mb-4 font-serif text-[length:clamp(44px,6vw,88px)] leading-[.94] font-light tracking-[-0.02em]">{!! $withLink($t('heading'), ':em', '<em class="text-brown italic">'.e($t('heading_em')).'</em>') !!}</h1>
            <p class="mb-10 max-w-[52ch] text-[16.5px] text-lead">
                {{ $t($needsDelivery ? 'lead_parcel' : 'lead_email') }}
            </p>

            <form method="post" action="{{ route('checkout.store') }}" novalidate x-data="checkout(@js($config))" x-on:submit="submit($event)" class="flex flex-wrap items-start gap-[clamp(24px,4vw,56px)]">
                @csrf
                <input type="hidden" name="expected_total" value="{{ $subtotal + $shippingGross }}" x-bind:value="total">

                <div class="min-w-0 flex-[1_1_480px]">
                    <div class="glass mb-4 rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]">
                        <h2 class="mb-4 flex items-baseline gap-3 font-serif text-[30px] leading-none font-light"><span aria-hidden="true" class="text-[22px] text-rose italic">i</span>{{ $t('contact') }}</h2>
                        <div class="grid gap-[13px]">
                            <x-shared::field name="phone" :label="$t('phone')" type="tel" autocomplete="tel" :hint="$t($needsDelivery ? 'phone_hint_parcel' : 'phone_hint')" />
                            <x-shared::field name="email" :label="$t('email')" type="email" autocomplete="email" :hint="$t('email_hint')" />
                            <x-shared::field name="name" :label="$t('name')" autocomplete="name" />
                        </div>

                        <details @if ($moreOpen) open @endif class="group mt-4 border-t border-line pt-4">
                            <summary class="flex cursor-pointer list-none justify-between gap-3 text-[13.5px] text-brown [&::-webkit-details-marker]:hidden">
                                <span>{{ $t($needsDelivery ? 'more_parcel' : 'more') }}</span>
                                <span aria-hidden="true" class="group-open:hidden">+</span><span aria-hidden="true" class="hidden group-open:inline">−</span>
                            </summary>
                            <div class="mt-3.5 grid animate-ma-up-quick gap-[13px]">
                                @if ($needsDelivery)
                                    <x-shared::field name="street" :label="$t('street')" autocomplete="street-address" />
                                    <div class="flex flex-wrap gap-[13px]">
                                        <x-shared::field name="postal_code" :label="$t('postal_code')" autocomplete="postal-code" inputmode="numeric" placeholder="30-001" class="flex-[0_1_130px]" />
                                        <x-shared::field name="city" :label="$t('city')" autocomplete="address-level2" class="flex-[1_1_150px]" />
                                    </div>
                                @endif
                                <x-shared::field name="invoice_nip" :label="$t('nip')" inputmode="numeric" />
                                @if ($needsDelivery)
                                    <x-shared::field name="note" :label="$t('note')" :placeholder="$t('note_placeholder')" />
                                @endif
                            </div>
                        </details>
                    </div>

                    <fieldset class="glass mb-4 rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]">
                        <legend class="float-left mb-4 flex w-full items-baseline gap-3 font-serif text-[30px] leading-none font-light"><span aria-hidden="true" class="text-[22px] text-rose italic">ii</span>{{ $t('payment') }}</legend>
                        <div class="grid gap-2.5">
                            @foreach ($paymentMethods as $method)
                                <label class="{{ $radio }} py-[15px] has-checked:border-navy has-checked:bg-navy-soft">
                                    <input type="radio" name="payment_method" value="{{ $method->value }}" x-model="payment" @checked($selectedPayment === $method->value) class="sr-only">
                                    <span class="grid size-4 flex-none place-items-center rounded-full border border-label"><span class="size-2 rounded-full group-has-checked:bg-navy"></span></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[15px]">{{ $method->title() }}</span>
                                        <span class="mt-0.5 block text-[12.5px] text-label">{{ $method->hint() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('payment_method')
                            <p class="mt-2 text-[13px] text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    {{-- BLIK only in złoty (PaymentMethod::for). --}}
                    @if ($paymentMethods->contains(PaymentMethod::Blik))
                        <div x-show="payment === 'blik'" x-transition:enter="transition duration-500 ease-clay" x-transition:enter-start="opacity-0 blur-sm -translate-y-2" class="glass mb-4 rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]">
                            <div class="mb-3.5 flex items-center gap-3">
                                <span class="rounded-full bg-navy px-3 py-[5px] text-[12px] font-medium tracking-[0.06em] text-white">BLIK</span>
                                <label for="blik_code" class="text-[13.5px] text-navy-text">{{ $t('blik_label') }}</label>
                            </div>
                            <input id="blik_code" name="blik_code" x-ref="blik" x-bind:value="blik"
                                   x-on:input="blik = $el.value = $el.value.replace(/\D/g, '').slice(0, 6)"
                                   inputmode="numeric" autocomplete="one-time-code" placeholder="• • • • • •" aria-describedby="blik_code-note"
                                   @error('blik_code') aria-invalid="true" @enderror
                                   x-bind:class="blik.length === 6 && 'border-navy shadow-[0_0_0_4px_rgb(36_65_126/.14)]'"
                                   @class([
                                       'w-full min-w-0 rounded-[18px] border bg-white p-[18px] text-center font-serif text-[36px] leading-none font-light tracking-[0.45em] text-ink tabular-nums transition-[border-color,box-shadow] duration-300 placeholder:text-line-strong focus:border-navy focus:shadow-[0_0_0_4px_rgb(36_65_126/.14)] focus:outline-none',
                                       'border-error' => $errors->has('blik_code'),
                                       'border-navy-line' => ! $errors->has('blik_code'),
                                   ])>
                            @error('blik_code')
                                <p id="blik_code-note" class="mt-2.5 text-center text-[13px] text-error">{{ $message }}</p>
                            @else
                                <p id="blik_code-note" x-text="blik.length === 6 ? @js($t('blik_ready')) : @js($t('blik_hint'))" class="mt-2.5 text-center text-[12.5px] text-navy-hint">{{ $t('blik_hint') }}</p>
                            @enderror
                        </div>
                    @endif

                    @if ($config['stripeKey'] && $paymentMethods->contains(PaymentMethod::Card))
                        {{-- Stripe draws the card field in its own frame, so the number never passes through this page. --}}
                        <div x-show="payment === 'card'" x-cloak class="glass mb-4 rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]">
                            <div class="mb-3.5 text-[13.5px] text-navy-text">{{ $t('card_label') }}</div>
                            <div x-ref="card" class="rounded-[18px] border border-navy-line bg-white p-[18px]"></div>
                            <p class="mt-2.5 text-[12.5px] text-label">{{ $t('card_hint') }}</p>
                        </div>
                    @endif

                    @if ($needsDelivery)
                        <fieldset class="glass rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]">
                            <legend class="float-left mb-4 flex w-full items-baseline gap-3 font-serif text-[30px] leading-none font-light"><span aria-hidden="true" class="text-[22px] text-rose italic">iii</span>{{ $t('delivery_way') }}</legend>
                            <div class="grid gap-2.5">
                                @foreach ($methods as $method)
                                    <label class="{{ $radio }} py-4 has-checked:border-ink has-checked:bg-sand-dark">
                                        <input type="radio" name="shipping_method" value="{{ $method['code'] }}" x-model="shipping" @checked($selectedShipping === $method['code']) class="sr-only">
                                        <span class="grid size-4 flex-none place-items-center rounded-full border border-label"><span class="size-2 rounded-full group-has-checked:bg-ink"></span></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[15px]">{{ $method['label'] }}</span>
                                            @if ($method['note'])
                                                <span class="mt-0.5 block text-[12.5px] text-label">{{ $method['note'] }}</span>
                                            @endif
                                        </span>
                                        <span class="text-[14px]">{{ $method['cost'] === 0 ? $t('free') : $money($method['cost']) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('shipping_method')
                                <p class="mt-2 text-[13px] text-error">{{ $message }}</p>
                            @enderror
                        </fieldset>
                    @else
                        {{-- Vouchers sent as PDFs alone: the e-mail is the delivery, so there is nothing to choose. --}}
                        <div class="glass rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]">
                            <div class="mb-4 flex items-baseline gap-3 font-serif text-[30px] leading-none font-light"><span aria-hidden="true" class="text-[22px] text-rose italic">iii</span>{{ $t('delivery') }}</div>
                            <div class="flex items-center gap-3.5 rounded-[18px] border border-ink bg-cream px-[18px] py-4">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[15px]">{{ $t('delivery_email') }}</span>
                                    <span class="mt-0.5 block text-[12.5px] text-label">{{ $t('delivery_email_note') }}</span>
                                </span>
                                <span class="text-[14px]">{{ $t('free') }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="min-w-[260px] flex-[1_1_340px] min-[900px]:sticky min-[900px]:top-24">
                    <div class="glass rounded-[26px] p-[clamp(20px,3vw,30px)]">
                        <h2 class="mb-[18px] font-serif text-[30px] leading-none font-light">{{ $t('your_order') }}</h2>
                        @foreach ($lines as $line)
                            <div class="mb-3.5 border-b border-line pb-3.5">
                                <div class="flex gap-3">
                                    @if ($thumbnail = $line->thumbnailUrl())
                                        <img src="{{ $thumbnail }}" alt="{{ $line->thumbnailAlt() }}" width="52" height="64" class="h-[70px] w-[58px] flex-none rounded-[12px] object-cover">
                                    @else
                                        <div class="h-[70px] w-[58px] flex-none rounded-[12px] bg-linen"></div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="font-serif text-[19px] leading-[1.2] font-light">{{ $line->name() }}</div>
                                        <div class="mt-0.5 text-[12px] [overflow-wrap:anywhere] text-label">{{ collect([$line->details(), $t('pieces', ['count' => $line->quantity])])->filter()->join(' · ') }}</div>
                                    </div>
                                    <div class="text-[13.5px] whitespace-nowrap">{{ $money($line->total()) }}</div>
                                </div>
                                {{-- Terms §4.5: a feature nobody would expect is accepted on its own, next to the product. --}}
                                @if ($deviations = $line->deviations())
                                    @php
                                        $field = 'accept_deviations.'.$line->key;
                                    @endphp
                                    <label class="mt-2.5 flex cursor-pointer items-start gap-2.5 rounded-[14px] bg-alert px-3 py-2.5 text-[13px] leading-[1.45] text-alert-text">
                                        <input type="checkbox" name="accept_deviations[{{ $line->key }}]" value="1" data-deviation x-model="deviations[@js($line->key)]" @checked(old($field))
                                               @error($field) aria-invalid="true" aria-describedby="{{ Str::slug($field) }}-error" @enderror
                                               class="mt-0.5 size-4 flex-none accent-ink">
                                        <span>{{ $t('accept_deviation', ['features' => collect($deviations)->map(fn (string $text, string $name) => ($name === $line->name() ? '' : $name.' — ').Str::lcfirst(rtrim($text, '. ')))->join('; ')]) }}</span>
                                    </label>
                                    @error($field)
                                        <p id="{{ Str::slug($field) }}-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                    @enderror
                                @endif
                            </div>
                        @endforeach
                        <div class="mb-[7px] flex justify-between text-[14px] text-muted"><span>{{ $t('items') }}</span><span>{{ $money($subtotal) }}</span></div>
                        <div class="mb-3.5 flex justify-between text-[14px] text-muted">
                            <span>{{ $t('delivery') }}</span>
                            <span x-text="shippingCost === 0 ? @js($t('free')) : $store.cart.format(shippingCost)">{{ $shippingGross === 0 ? $t('free') : $money($shippingGross) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between border-t border-line pt-3.5">
                            <span class="text-[14.5px]">{{ $t('total') }}</span>
                            <span x-text="$store.cart.format(total)" class="font-serif text-[40px] leading-none font-light tabular-nums">{{ $money($subtotal + $shippingGross) }}</span>
                        </div>

                        @if (session('checkout_notice'))
                            <p role="alert" class="mt-4 rounded-[14px] border border-alert-line bg-alert px-3.5 py-3 text-[13.5px] text-alert-text">{{ session('checkout_notice') }}</p>
                        @endif

                        @if (Route::has('content.terms'))
                            <label class="mt-2 -mb-3 flex cursor-pointer items-start gap-2.5 py-3 text-[13.5px] leading-[1.5] text-graphite">
                                <input type="checkbox" name="accept_terms" value="1" x-ref="terms" x-model="accepted" @checked(old('accept_terms'))
                                       @error('accept_terms') aria-invalid="true" aria-describedby="accept-terms-error" @enderror
                                       class="mt-[3px] size-4 flex-none accent-ink">
                                <span>{!! $withLink($t('accept_terms'), ':link', '<a href="'.e(route('content.terms')).'" target="_blank" rel="noopener">'.e($t('terms_link')).'</a>') !!}</span>
                            </label>
                            @error('accept_terms')
                                <p id="accept-terms-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                            @enderror
                            @if (Route::has('content.privacy'))
                                <p class="mt-2 text-[12px] leading-[1.5] text-hint">{!! $withLink($t('privacy'), ':link', '<a href="'.e(route('content.privacy')).'" target="_blank" rel="noopener">'.e($t('privacy_link')).'</a>') !!}</p>
                            @endif
                        @endif

                        {{-- The button breathes once the form is ready (mellowaura-design, „Kod BLIK”). --}}
                        <button type="submit" x-bind:class="ready ? 'animate-[auraRing_1.8s_ease-in-out_infinite]' : 'bg-label!'"
                                class="fill-btn mt-5 min-h-[60px] w-full rounded-full bg-navy p-[17px] text-[15px] font-medium tracking-[0.02em] text-linen transition duration-300 [--fill:var(--color-ink)] hover:bg-ink active:scale-[.97]">
                            <span x-text="label">{{ $t('pay', ['amount' => $money($subtotal + $shippingGross)]) }}</span>
                        </button>
                        <div class="mt-3.5 flex flex-wrap justify-center gap-3 text-[11.5px] tracking-[0.08em] text-label uppercase">
                            {!! collect($t('badges'))->map(fn (string $badge) => '<span>'.e($badge).'</span>')->join('<span>&middot;</span>') !!}
                        </div>
                        @if ($needsDelivery)
                            <p class="mt-3 text-center text-[12px] leading-[1.5] text-hint">{{ $t('wrapping') }}</p>
                        @endif
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-shared::layout>
