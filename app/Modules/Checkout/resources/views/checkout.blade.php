@use('App\Modules\Checkout\Enums\PaymentMethod')
@use('App\Modules\Shared\Support\Money')
@php
    $more = ['street', 'postal_code', 'city', 'invoice_nip', 'note'];
    $moreOpen = $errors->hasAny($more) || collect($more)->contains(fn (string $field) => filled(old($field)));
    $radio = 'group flex cursor-pointer items-center gap-3.5 rounded-[4px] border border-line bg-cream px-[18px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
@endphp

<x-shared::layout title="Zamówienie | MellowAura" :noindex="true">
    @if ($analytics)
        <x-consent::analytics-event name="begin_checkout" :params="$analytics" />
    @endif
    <div class="mx-auto max-w-[1000px] animate-ma-view px-7 pt-14 pb-24">
        @if ($lines->isEmpty())
            <div class="py-16 text-center">
                <h1 class="mb-4 font-serif text-[length:clamp(32px,4.4vw,52px)] leading-[1.04] font-light">Nic tu jeszcze nie ma</h1>
                <p class="mx-auto mb-8 max-w-[46ch] text-[17px] leading-[1.7] text-lead">Zobacz, co czeka w pracowni. Każda sztuka jest jedna.</p>
                <a href="{{ route('shop.index') }}" class="inline-block rounded-full bg-ink px-[30px] py-[15px] text-[14px] text-linen hover:bg-navy hover:text-linen">Zobacz produkty</a>
            </div>
        @else
            <div class="mb-6 flex items-center gap-2.5 text-[11.5px] tracking-[0.16em] text-label uppercase">
                <span class="size-[7px] rounded-full bg-navy"></span><span>jeden ekran &middot; zapłacisz blikiem</span>
            </div>
            <h1 class="mb-2.5 font-serif text-[length:clamp(32px,4.4vw,50px)] leading-[1.06] font-light">Jeszcze trzy pola<br>i gotowe</h1>
            <p class="mb-[34px] max-w-[46ch] text-[16px] text-muted">Bez zakładania konta. Paczkomat dobiorę po numerze telefonu — resztę danych podasz tylko, jeśli zechcesz.</p>

            <form method="post" action="{{ route('checkout.store') }}" novalidate x-data="checkout(@js($config))" x-on:submit="submit($event)" class="flex flex-wrap gap-11">
                @csrf
                <input type="hidden" name="expected_total" value="{{ $subtotal + $shippingGross }}" x-bind:value="total">

                <div class="min-w-0 flex-[1_1_340px]">
                    <div class="mb-[26px] rounded-[4px] border border-divider bg-cream px-[22px] py-6">
                        <div class="grid gap-[13px]">
                            <x-shared::field name="phone" label="Telefon" type="tel" autocomplete="tel" hint="Po nim znajdę Twój paczkomat" />
                            <x-shared::field name="email" label="E-mail" type="email" autocomplete="email" hint="Wyślę na niego potwierdzenie" />
                            <x-shared::field name="name" label="Imię i nazwisko" autocomplete="name" />
                        </div>

                        <details @if ($moreOpen) open @endif class="group mt-4 border-t border-sand-dark pt-4">
                            <summary class="flex cursor-pointer list-none justify-between gap-3 text-[13.5px] text-brown [&::-webkit-details-marker]:hidden">
                                <span>Inny adres, faktura na firmę, dopisek do paczki</span>
                                <span aria-hidden="true" class="group-open:hidden">+</span><span aria-hidden="true" class="hidden group-open:inline">−</span>
                            </summary>
                            <div class="mt-3.5 grid animate-ma-up-quick gap-[13px]">
                                <x-shared::field name="street" label="Ulica i numer" autocomplete="street-address" />
                                <div class="flex flex-wrap gap-[13px]">
                                    <x-shared::field name="postal_code" label="Kod pocztowy" autocomplete="postal-code" inputmode="numeric" placeholder="30-001" class="flex-[0_1_130px]" />
                                    <x-shared::field name="city" label="Miasto" autocomplete="address-level2" class="flex-[1_1_150px]" />
                                </div>
                                <x-shared::field name="invoice_nip" label="NIP do faktury" inputmode="numeric" />
                                <x-shared::field name="note" label="Dopisek do paczki" placeholder="np. to prezent, dołóż kartkę" />
                            </div>
                        </details>
                    </div>

                    <fieldset class="mb-[18px]">
                        <legend class="mb-3 text-[11.5px] tracking-[0.16em] text-label uppercase">Płatność</legend>
                        <div class="grid gap-2.5">
                            @foreach (PaymentMethod::cases() as $method)
                                <label class="{{ $radio }} py-[15px] has-checked:border-navy has-checked:bg-navy-soft">
                                    <input type="radio" name="payment_method" value="{{ $method->value }}" x-model="payment" @checked($selectedPayment === $method->value) class="sr-only">
                                    <span class="grid size-4 flex-none place-items-center rounded-full border border-label"><span class="size-2 rounded-full group-has-checked:bg-navy"></span></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[15px]">{{ $method->label() }}</span>
                                        <span class="mt-0.5 block text-[12.5px] text-label">{{ $method->note() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('payment_method')
                            <p class="mt-2 text-[13px] text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div x-show="payment === 'blik'" class="mb-6 rounded-[4px] border border-navy-line bg-navy-soft px-5 py-[22px]">
                        <div class="mb-3.5 flex items-center gap-3">
                            <span class="rounded-[4px] bg-navy px-2.5 py-[5px] text-[12px] font-medium tracking-[0.06em] text-white">BLIK</span>
                            <label for="blik_code" class="text-[13.5px] text-navy-text">Przepisz kod z aplikacji banku</label>
                        </div>
                        <input id="blik_code" name="blik_code" x-ref="blik" x-bind:value="blik"
                               x-on:input="blik = $el.value = $el.value.replace(/\D/g, '').slice(0, 6)"
                               inputmode="numeric" autocomplete="one-time-code" placeholder="• • • • • •" aria-describedby="blik_code-note"
                               @error('blik_code') aria-invalid="true" @enderror
                               x-bind:class="blik.length === 6 && 'border-navy'"
                               @class([
                                   'w-full min-w-0 rounded-[4px] border bg-white p-[17px] text-center font-serif text-[26px] tracking-[0.4em] text-navy placeholder:text-navy-hint focus:border-navy',
                                   'border-error' => $errors->has('blik_code'),
                                   'border-navy-line' => ! $errors->has('blik_code'),
                               ])>
                        @error('blik_code')
                            <p id="blik_code-note" class="mt-2.5 text-center text-[13px] text-error">{{ $message }}</p>
                        @else
                            <p id="blik_code-note" x-text="blik.length === 6 ? 'Kod gotowy — potwierdzisz w aplikacji' : '6 cyfr, ważne 2 minuty'" class="mt-2.5 text-center text-[12.5px] text-navy-hint">6 cyfr, ważne 2 minuty</p>
                        @enderror
                    </div>

                    <fieldset>
                        <legend class="mb-3.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Sposób dostawy</legend>
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
                                    <span class="text-[14px]">{{ $method['cost'] === 0 ? 'gratis' : Money::format($method['cost']) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('shipping_method')
                            <p class="mt-2 text-[13px] text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>
                </div>

                <div class="min-w-[260px] flex-[0_1_300px]">
                    <div class="rounded-[4px] border border-divider bg-cream p-6">
                        <h2 class="mb-[18px] font-serif text-[20px]">Twoje zamówienie</h2>
                        @foreach ($lines as $line)
                            <div class="mb-3.5 border-b border-sand-dark pb-3.5">
                                <div class="flex gap-3">
                                    @if ($thumbnail = $line->thumbnailUrl())
                                        <img src="{{ $thumbnail }}" alt="{{ $line->thumbnailAlt() }}" class="h-16 w-[52px] flex-none rounded-[3px] object-cover">
                                    @else
                                        <div class="h-16 w-[52px] flex-none rounded-[3px] bg-line-soft"></div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[14px] leading-[1.3]">{{ $line->name() }}</div>
                                        <div class="mt-0.5 text-[12px] [overflow-wrap:anywhere] text-label">{{ collect([$line->details(), $line->quantity.' szt.'])->filter()->join(' · ') }}</div>
                                    </div>
                                    <div class="text-[13.5px] whitespace-nowrap">{{ Money::format($line->total()) }}</div>
                                </div>
                                {{-- Terms §4.5: a feature nobody would expect is accepted on its own, next to the product. --}}
                                @if ($deviations = $line->deviations())
                                    @php
                                        $field = 'accept_deviations.'.$line->key;
                                    @endphp
                                    <label class="mt-2.5 flex cursor-pointer items-start gap-2.5 rounded-[4px] bg-alert px-3 py-2.5 text-[13px] leading-[1.45] text-alert-text">
                                        <input type="checkbox" name="accept_deviations[{{ $line->key }}]" value="1" data-deviation x-model="deviations[@js($line->key)]" @checked(old($field))
                                               @error($field) aria-invalid="true" aria-describedby="{{ Str::slug($field) }}-error" @enderror
                                               class="mt-0.5 size-4 flex-none accent-ink">
                                        <span>Akceptuję: {{ collect($deviations)->map(fn (string $text, string $name) => ($name === $line->name() ? '' : $name.' — ').Str::lcfirst(rtrim($text, '. ')))->join('; ') }}</span>
                                    </label>
                                    @error($field)
                                        <p id="{{ Str::slug($field) }}-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                    @enderror
                                @endif
                            </div>
                        @endforeach
                        <div class="mb-[7px] flex justify-between text-[14px] text-muted"><span>Produkty</span><span>{{ Money::format($subtotal) }}</span></div>
                        <div class="mb-3.5 flex justify-between text-[14px] text-muted">
                            <span>Dostawa</span>
                            <span x-text="shippingCost === 0 ? 'gratis' : $store.cart.format(shippingCost)">{{ $shippingGross === 0 ? 'gratis' : Money::format($shippingGross) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-divider pt-3.5 font-serif text-[21px]">
                            <span>Razem</span>
                            <span x-text="$store.cart.format(total)">{{ Money::format($subtotal + $shippingGross) }}</span>
                        </div>

                        @if (session('checkout_notice'))
                            <p role="alert" class="mt-4 rounded-[4px] border border-alert-line bg-alert px-3.5 py-3 text-[13.5px] text-alert-text">{{ session('checkout_notice') }}</p>
                        @endif

                        @if (Route::has('content.terms'))
                            <label class="mt-2 -mb-3 flex cursor-pointer items-start gap-2.5 py-3 text-[13.5px] leading-[1.5] text-graphite">
                                <input type="checkbox" name="accept_terms" value="1" x-ref="terms" x-model="accepted" @checked(old('accept_terms'))
                                       @error('accept_terms') aria-invalid="true" aria-describedby="accept-terms-error" @enderror
                                       class="mt-[3px] size-4 flex-none accent-ink">
                                <span>Akceptuję <a href="{{ route('content.terms') }}" target="_blank" rel="noopener">regulamin sklepu</a></span>
                            </label>
                            @error('accept_terms')
                                <p id="accept-terms-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                            @enderror
                            @if (Route::has('content.privacy'))
                                <p class="mt-2 text-[12px] leading-[1.5] text-hint">Dane zapisuję tylko na czas realizacji — szczegóły w <a href="{{ route('content.privacy') }}" target="_blank" rel="noopener">polityce prywatności</a>.</p>
                            @endif
                        @endif

                        <button type="submit" x-bind:class="ready || 'bg-label!'"
                                class="mt-5 w-full rounded-full bg-navy p-[17px] text-[15px] tracking-[0.02em] text-linen transition duration-300 hover:bg-ink active:scale-[.97]">
                            <span x-text="label">Płacę {{ Money::format($subtotal + $shippingGross) }}</span>
                        </button>
                        <div class="mt-3.5 flex flex-wrap justify-center gap-3 text-[11.5px] tracking-[0.08em] text-label uppercase">
                            <span>Apple Pay</span><span>&middot;</span><span>Google Pay</span><span>&middot;</span><span>Przelewy24</span>
                        </div>
                        <p class="mt-3 text-center text-[12px] leading-[1.5] text-hint">Ceramikę zawijam w wióry i podwójny karton</p>
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-shared::layout>
