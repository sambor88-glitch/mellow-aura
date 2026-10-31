@use('App\Modules\Checkout\Enums\PaymentMethod')
@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\Money')
@php
    // Every amount here is in the currency of the basket.
    $money = fn (int $amount) => Money::format($amount, Locales::currency());
    $t = fn (string $key, array $replace = []) => __('checkout::checkout.'.$key, $replace);
    // A sentence with markup in it: the sentence is escaped, the markup put in place of its placeholder.
    $withLink = fn (string $sentence, string $placeholder, string $html) => str_replace($placeholder, $html, e($sentence));
    // Vouchers sent as PDFs alone need no note for the parcel, only an invoice may. The address sits with the courier.
    $more = $needsDelivery ? ['invoice_nip', 'note'] : ['invoice_nip'];
    $moreOpen = $errors->hasAny($more) || collect($more)->contains(fn (string $field) => filled(old($field)));
    $radio = 'group flex cursor-pointer items-center gap-3.5 rounded-[20px] border border-line bg-cream/60 px-[18px] transition-[border-color,background-color,transform] duration-300 hover:-translate-y-0.5 hover:border-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
@endphp

<x-shared::layout :title="$t('title')" :noindex="true">
    @if ($analytics)
        <x-consent::analytics-event name="begin_checkout" :params="$analytics" />
    @endif
    <div class="mx-auto max-w-[1180px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-12 pb-24">
        @if ($lines->isEmpty())
            <div class="py-16 text-center">
                <h1 class="mb-4 font-serif text-[length:clamp(44px,6vw,88px)] leading-[.96] font-light tracking-[-0.02em]">{{ $t('empty_title') }}</h1>
                <p class="mx-auto mb-8 max-w-[46ch] text-[17px] leading-[1.7] text-lead">{{ $t('empty_text') }}</p>
                <x-shared::button href="{{ route('shop.index') }}">{{ $t('empty_cta') }}</x-shared::button>
            </div>
        @else
            <p class="eyebrow mb-[18px]">{!! $t('eyebrow') !!}</p>
            <h1 class="mb-4 font-serif text-[length:clamp(44px,6vw,88px)] leading-[.94] font-light tracking-[-0.02em]">{!! $withLink($t('heading'), ':em', '<em class="text-brown italic">'.e($t('heading_em')).'</em>') !!}</h1>
            <p class="mb-10 max-w-[52ch] text-[16.5px] text-lead">
                {{ $t($needsDelivery ? 'lead_parcel' : 'lead_email') }}
            </p>

            <form method="post" action="{{ route('checkout.store') }}" novalidate
                  @if ($acceptsVouchers) data-voucher-add="{{ route('checkout.voucher.add') }}" data-voucher-remove="{{ route('checkout.voucher.remove') }}" @endif
                  x-data="checkout(@js($config))" x-on:submit="submit($event)" class="flex flex-wrap items-start gap-[clamp(24px,4vw,56px)]">
                @csrf
                <input type="hidden" name="expected_total" value="{{ $subtotal + $shippingGross }}" x-bind:value="total">
                <input type="hidden" name="expected_vouchers" value="{{ $vouchers->sum('amount') }}" x-bind:value="vouchersPaid">

                <div class="min-w-0 flex-[1_1_480px]">
                    <div class="glass mb-4 rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]">
                        <h2 class="mb-4 flex items-baseline gap-3 font-serif text-[30px] leading-none font-light"><span aria-hidden="true" class="text-[22px] text-rose italic">i</span>{{ $t('contact') }}</h2>
                        <div class="grid gap-[13px]">
                            <x-shared::field name="email" :label="$t('email')" type="email" autocomplete="email" :hint="$t('email_hint')" aria-required="true" />
                            <x-shared::field name="phone" :label="$t('phone')" type="tel" autocomplete="tel" :hint="$t($needsDelivery ? 'phone_hint_parcel' : 'phone_hint')" aria-required="true" />
                            <x-shared::field name="name" :label="$t('name')" autocomplete="name" aria-required="true" />
                        </div>

                        <details @if ($moreOpen) open @endif class="group mt-4 border-t border-line pt-1">
                            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 text-[13.5px] text-brown [&::-webkit-details-marker]:hidden">
                                <span>{{ $t($needsDelivery ? 'more_parcel' : 'more') }}</span>
                                <span aria-hidden="true" class="group-open:hidden">+</span><span aria-hidden="true" class="hidden group-open:inline">−</span>
                            </summary>
                            <div class="mt-3.5 grid animate-ma-up-quick gap-[13px]">
                                <x-shared::field name="invoice_nip" :label="$t('nip')" inputmode="numeric" :optional="true" />
                                @if ($needsDelivery)
                                    <x-shared::field name="note" :label="$t('note')" :placeholder="$t('note_placeholder')" :optional="true" />
                                @endif
                            </div>
                        </details>
                    </div>

                    @if ($needsDelivery)
                        <fieldset class="glass mb-4 rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]">
                            <legend class="float-left mb-4 flex w-full items-baseline gap-3 font-serif text-[30px] leading-none font-light"><span aria-hidden="true" class="text-[22px] text-rose italic">ii</span>{{ $t('delivery_way') }}</legend>
                            <div class="clear-both grid gap-2.5">
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

                            {{-- Required: without the locker's code Kasia has nowhere to send the parcel (MA-248). --}}
                            @if ($methods->has('parcel_locker'))
                                <div x-show="shipping === 'parcel_locker'" @if ($selectedShipping !== 'parcel_locker') x-cloak @endif class="mt-4 border-t border-line pt-4">
                                    <x-shared::field name="locker_code" :label="$t('locker')" :hint="$t($config['lockerMap'] ? 'locker_hint_map' : 'locker_hint')" aria-required="true" x-ref="locker" x-model="locker" x-on:input="lockerAddress = ''"
                                                     autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="16" :placeholder="$t('locker_placeholder')" />
                                    @if ($config['lockerMap'])
                                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                                            <x-shared::button variant="secondary" size="md" type="button" x-on:click="openLockerMap()">
                                                {{ $t('locker_map') }}
                                            </x-shared::button>
                                            <p x-text="lockerAddress" aria-live="polite" class="min-w-0 flex-1 text-[13px] text-label"></p>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- The courier needs a door; the request ignores the address for every other way. --}}
                            @if ($methods->has('courier'))
                                <div x-show="shipping === 'courier'" @if ($selectedShipping !== 'courier') x-cloak @endif class="mt-4 grid gap-[13px] border-t border-line pt-4">
                                    <p class="text-[13.5px] text-brown">{{ $t('courier_address') }}</p>
                                    <x-shared::field name="street" :label="$t('street')" autocomplete="address-line1" aria-required="true" />
                                    <div class="flex flex-wrap gap-[13px]">
                                        {{-- The phone's number pad has no dash, so the dash comes by itself after two digits. --}}
                                        <x-shared::field name="postal_code" :label="$t('postal_code')" autocomplete="postal-code" inputmode="numeric" placeholder="30-001" aria-required="true" class="flex-[0_1_130px]"
                                                         x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, 5).replace(/^(\d{2})(\d)/, '$1-$2')" />
                                        <x-shared::field name="city" :label="$t('city')" autocomplete="address-level2" aria-required="true" class="flex-[1_1_150px]" />
                                    </div>
                                </div>
                            @endif
                        </fieldset>

                        @if ($config['lockerMap'] && $methods->has('parcel_locker'))
                            {{-- InPost's own map, drawn on the first opening (resources/js/checkout.js). Esc, × or a click beside it closes it. --}}
                            <dialog x-ref="lockers" aria-labelledby="lockers-title" x-on:click.self="$el.close()"
                                    class="fixed inset-0 size-full max-h-none max-w-none animate-ma-in items-center justify-center bg-ink/28 pad-safe [--pad:10px] text-ink backdrop-blur-[6px] open:flex backdrop:bg-transparent">
                                <div class="flex h-[min(760px,100%)] w-[min(1080px,100%)] flex-col overflow-hidden rounded-[26px] border border-glass-line bg-cream">
                                    <div class="flex items-center justify-between gap-3 border-b border-line px-[clamp(16px,3vw,26px)] py-3">
                                        <h2 id="lockers-title" class="font-serif text-[28px] leading-none font-light">{{ $t('locker_dialog') }}</h2>
                                        <button type="button" x-on:click="$refs.lockers.close()" aria-label="{{ $t('locker_close') }}"
                                                class="grid size-11 place-items-center rounded-full border border-line-strong text-[20px] leading-none text-ink transition-colors duration-300 hover:border-ink hover:bg-sand-dark">×</button>
                                    </div>
                                    <div x-ref="lockerMap" class="min-h-0 flex-1"></div>
                                </div>
                            </dialog>
                        @endif
                    @else
                        {{-- Vouchers sent as PDFs alone: the e-mail is the delivery, so there is nothing to choose — and nothing looks chosen. --}}
                        <div class="glass mb-4 rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]">
                            <h2 class="mb-4 flex items-baseline gap-3 font-serif text-[30px] leading-none font-light"><span aria-hidden="true" class="text-[22px] text-rose italic">ii</span>{{ $t('delivery') }}</h2>
                            <div class="flex items-center gap-3.5 rounded-[18px] bg-cream/60 px-[18px] py-4">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[15px]">{{ $t('delivery_email') }}</span>
                                    <span class="mt-0.5 block text-[12.5px] text-label">{{ $t('delivery_email_note') }}</span>
                                </span>
                                <span class="text-[14px]">{{ $t('free') }}</span>
                            </div>
                        </div>
                    @endif

                    {{-- Vouchers that pay for everything leave nothing to choose (MA-211). --}}
                    <fieldset x-show="due > 0" @if ($vouchers->sum('amount') >= $subtotal + $shippingGross) x-cloak @endif class="glass rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]">
                        <legend class="float-left mb-4 flex w-full items-baseline gap-3 font-serif text-[30px] leading-none font-light"><span aria-hidden="true" class="text-[22px] text-rose italic">iii</span>{{ $t('payment') }}</legend>
                        <div class="clear-both grid gap-2.5">
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
                </div>

                {{-- Pinned only as far as the screen reaches: taller than the screen, it scrolls on its own, so „Płacę” is never out of reach. --}}
                <div class="min-w-[min(100%,260px)] flex-[1_1_340px] min-[900px]:sticky min-[900px]:top-24 min-[900px]:max-h-[calc(100dvh-7rem)] min-[900px]:overflow-y-auto min-[900px]:overscroll-contain min-[900px]:rounded-[26px]">
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
                        @if ($acceptsVouchers)
                            {{-- Amount vouchers (MA-211): rare, so folded away like the invoice (mellowaura-ux). Each code
                                 pays as much as it holds; the screen splits a new delivery cost between them as the server does. --}}
                            @foreach ($vouchers as $use)
                                @php
                                    $code = $use['voucher']->code;
                                @endphp
                                <div class="mb-[7px] flex items-start justify-between gap-3 text-[14px] text-muted">
                                    <span class="min-w-0">
                                        <span class="block">{{ $t('voucher_line', ['code' => $code]) }}</span>
                                        <span class="block text-[12px] text-label" x-text="voucherLeftLabel(@js($code))">{{ $use['voucher']->balance_minor === $use['amount'] ? $t('voucher_used_up') : $t('voucher_left', ['amount' => $money($use['voucher']->balance_minor - $use['amount'])]) }}</span>
                                        <button type="button" x-on:click="removeVoucher(@js($code))" class="-ml-1 min-h-11 px-1 text-[12.5px] text-brown underline underline-offset-2 hover:text-ink">{{ $t('voucher_remove', ['code' => $code]) }}</button>
                                    </span>
                                    <span class="whitespace-nowrap tabular-nums" x-text="'− ' + $store.cart.format(voucherAmount(@js($code)))">−&thinsp;{{ $money($use['amount']) }}</span>
                                </div>
                            @endforeach
                            @if (session('voucher_added'))
                                <p role="status" class="sr-only">{{ $t('voucher_added', ['code' => session('voucher_added')]) }}</p>
                            @endif
                            <details @if ($errors->has('voucher_code')) open @endif class="group mb-3.5">
                                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 text-[13.5px] text-brown [&::-webkit-details-marker]:hidden">
                                    <span>{{ $t('voucher_toggle') }}</span>
                                    <span aria-hidden="true" class="group-open:hidden">+</span><span aria-hidden="true" class="hidden group-open:inline">−</span>
                                </summary>
                                <div class="mt-1.5 animate-ma-up-quick">
                                    <x-shared::field name="voucher_code" :label="$t('voucher_label')" :hint="$t('voucher_hint')" x-ref="voucherCode"
                                                     x-on:keydown.enter.prevent="addVoucher()" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="30"
                                                     :placeholder="$t('voucher_placeholder')" />
                                    {{-- A button, not a second submit: Enter anywhere else in the form still means „Płacę”. --}}
                                    <x-shared::button variant="secondary" size="md" type="button" x-on:click="addVoucher()" class="mt-2.5">
                                        {{ $t('voucher_add') }}
                                    </x-shared::button>
                                </div>
                            </details>
                        @endif
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 border-t border-line pt-3.5">
                            <span class="text-[14.5px]" x-text="vouchersPaid > 0 ? @js($t('to_pay')) : @js($t('total'))">{{ $vouchers->sum('amount') > 0 ? $t('to_pay') : $t('total') }}</span>
                            <span x-text="$store.cart.format(due)" class="font-serif text-[length:clamp(30px,10vw,40px)] leading-none font-light whitespace-nowrap tabular-nums">{{ $money($subtotal + $shippingGross - $vouchers->sum('amount')) }}</span>
                        </div>
                        <p x-show="vouchersPaid > 0 && due === 0" @if ($subtotal + $shippingGross > $vouchers->sum('amount')) x-cloak @endif class="mt-2 text-[13px] text-label">{{ $t('voucher_covers') }}</p>

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

                        {{-- BLIK only in złoty (PaymentMethod::for), and its code here only with BLIK Level 0.
                             The code lives 2 minutes, so it is the last thing typed, right above the button. --}}
                        @if ($blikCode && $paymentMethods->contains(PaymentMethod::Blik))
                            @php
                                // The code is never flashed back: after any mistake elsewhere it has to be typed again.
                                $elsewhere = collect($errors->keys())->contains(fn (string $field) => $field !== 'blik_code');
                                $blikError = $elsewhere ? $t('blik_retype') : $errors->first('blik_code');
                                $blikNote = $blikError ?: $t('blik_hint');
                            @endphp
                            <div x-show="payment === 'blik' && due > 0" x-transition:enter="transition duration-500 ease-clay" x-transition:enter-start="opacity-0 blur-sm -translate-y-2"
                                 @if ($selectedPayment !== PaymentMethod::Blik->value) x-cloak @endif class="mt-5 rounded-[20px] border border-navy-line bg-navy-soft p-4">
                                <div class="mb-3 flex items-center gap-3">
                                    <span class="rounded-full bg-navy px-3 py-[5px] text-[12px] font-medium tracking-[0.06em] text-white">BLIK</span>
                                    <label for="blik_code" class="text-[13.5px] text-navy-text">{{ $t('blik_label') }}</label>
                                </div>
                                <input id="blik_code" name="blik_code" x-ref="blik" x-bind:value="blik"
                                       x-on:input="blik = $el.value = $el.value.replace(/\D/g, '').slice(0, 6)"
                                       inputmode="numeric" autocomplete="one-time-code" placeholder="••••••" aria-describedby="blik_code-note"
                                       @if ($blikError) aria-invalid="true" @endif
                                       x-bind:class="blik.length === 6 && 'border-navy shadow-[0_0_0_4px_rgb(36_65_126/.14)]'"
                                       @class([
                                           'w-full min-w-0 rounded-[14px] border bg-white px-3 py-4 text-center font-serif text-[34px] leading-none font-light tracking-[0.4em] text-ink tabular-nums transition-[border-color,box-shadow] duration-300 placeholder:text-line-strong focus:border-navy focus:shadow-[0_0_0_4px_rgb(36_65_126/.14)] focus:outline-none',
                                           'border-error' => $blikError,
                                           'border-navy-line' => ! $blikError,
                                       ])>
                                <p id="blik_code-note" x-text="blik.length === 6 ? @js($t('blik_ready')) : @js($blikNote)" x-bind:class="blik.length === 6 && 'text-navy-hint!'"
                                   @class(['mt-2.5 text-center', 'text-[13px] text-error' => $blikError, 'text-[12.5px] text-navy-hint' => ! $blikError])>{{ $blikNote }}</p>
                            </div>
                        @endif

                        {{-- The button breathes once the form is ready (mellowaura-design, „Kod BLIK”). --}}
                        <x-shared::button variant="pay" x-bind:class="ready ? 'animate-[auraRing_1.8s_ease-in-out_infinite]' : 'bg-label!'" class="mt-5">
                            <span x-text="label">{{ $t('pay', ['amount' => $money($subtotal + $shippingGross - $vouchers->sum('amount'))]) }}</span>
                        </x-shared::button>
                        <div class="mt-3.5 flex flex-wrap justify-center gap-3 text-[11.5px] tracking-[0.08em] text-label uppercase">
                            {!! collect(config('services.przelewy24.wallets') ? __('checkout::checkout.wallet_badges') : [])->merge($t('badges'))->map(fn (string $badge) => '<span>'.e($badge).'</span>')->join('<span>&middot;</span>') !!}
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
