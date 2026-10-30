@use('App\Modules\Checkout\Enums\PaymentMethod')
@use('App\Modules\Shared\Support\Money')
@use('App\Modules\Workshops\Models\WorkshopBooking')
@php
    $when = $date->dayLabel().', '.$date->timeLabel();
    $price = $workshop['price_gross'];
    $seats = (int) old('seats', 1);
    $seats = $seats >= 1 && $seats <= max(1, $maxSeats) ? $seats : 1;
    // A workshop voucher books by itself (MA-137); amount vouchers pay their part of the sum (MA-244).
    $byWorkshopVoucher = $workshopVoucher !== null;
    $seats = $byWorkshopVoucher ? $workshopVoucher->seats : $seats;
    $voucherUses = \App\Modules\Gifts\Support\VoucherBalance::split($amountVouchers, $price * $seats);
    $vouchersPaid = (int) $voucherUses->sum('amount');
    $due = $price * $seats - $vouchersPaid;
    $link = 'min-h-11 text-[12.5px] text-brown underline underline-offset-2 hover:text-ink';
    $glass = 'glass mb-4 rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]';
    $radio = 'group flex cursor-pointer items-center gap-3.5 rounded-[18px] border border-line bg-cream/60 px-[18px] py-[15px] transition-[border-color,background-color,transform] duration-300 hover:-translate-y-0.5 hover:border-ink has-checked:border-navy has-checked:bg-navy-soft has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
@endphp

<x-shared::layout :title="'Zapis: '.$workshop['name'].' | MellowAura'" :noindex="true">
    <div class="mx-auto max-w-[1180px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-12 pb-24">
        <p class="eyebrow mb-[18px]">warsztaty &middot; zapis</p>
        <h1 class="mb-4 font-serif text-[length:clamp(40px,5.6vw,80px)] leading-[.96] font-light tracking-[-0.02em] text-balance">{{ $workshop['name'] }}</h1>
        <p class="mb-10 max-w-[52ch] text-[16.5px] text-lead">
            <time datetime="{{ $date->starts_at->toIso8601String() }}">{{ $when }}</time>@if ($workshop['duration_label']) &middot; {{ $workshop['duration_label'] }}@endif.
            Miejsce jest Twoje, gdy tylko bank potwierdzi płatność{{ $takesVouchers ? ' — albo od razu, gdy płaci voucher' : '' }}.
        </p>

        @if ($seatsLeft === 0)
            <div class="{{ $glass }} max-w-[620px]">
                <h2 class="mb-3 font-serif text-[30px] leading-none font-light">Ten termin jest już pełny</h2>
                <p class="mb-5 text-[15px] leading-[1.65] text-lead">Wybierz inny termin albo napisz do mnie — gdy ktoś zrezygnuje, dam znać.</p>
                <a href="{{ route('workshops.index') }}#workshop-dates" class="inline-flex min-h-[52px] items-center rounded-full bg-ink px-[30px] text-[14px] text-linen transition duration-300 hover:bg-rose hover:text-ink">Wybieram inny termin</a>
            </div>
        @else
            <form method="post" action="{{ route('workshops.booking.store', $date) }}" novalidate data-submit-once x-on:pageshow.window="if ($event.persisted) sending = false"
                  x-data="{
                      seats: {{ $seats }}, price: {{ $byWorkshopVoucher ? 0 : $price }}, payment: @js($selectedPayment), blik: '', sending: false,
                      addVoucherTo: @js(route('workshops.booking.voucher.add', $date)), removeVoucherTo: @js(route('workshops.booking.voucher.remove', $date)),
                      vouchers: @js($amountVouchers->map(fn ($voucher) => ['code' => $voucher->code, 'balance' => $voucher->balance_minor])->values()),
                      get total() { return this.seats * this.price },
                      {{-- What each amount voucher pays, in the order typed — the same split as Gifts\Support\VoucherBalance. --}}
                      get split() { let left = this.total; return this.vouchers.map((voucher) => { const amount = Math.min(left, voucher.balance); left -= amount; return amount }) },
                      get vouchersPaid() { return this.split.reduce((sum, amount) => sum + amount, 0) },
                      get due() { return this.total - this.vouchersPaid },
                      leftOn(index) { const left = this.vouchers[index].balance - this.split[index]; return left === 0 ? 'cały idzie na ten zapis' : 'zostanie na nim ' + this.format(left) },
                      format(amount) { return this.$store.cart.format(amount) },
                      {{-- The whole form goes to the voucher's address, so everything typed so far comes back filled in. --}}
                      send(address, extra = {}) {
                          if (this.sending || this.$root.hasAttribute('data-sending')) return;
                          Object.entries(extra).forEach(([name, value]) => this.$root.append(Object.assign(document.createElement('input'), { type: 'hidden', name, value })));
                          this.$root.action = address;
                          this.sending = true;
                          this.$root.submit();
                      },
                  }"
                  class="flex flex-wrap items-start gap-[clamp(24px,4vw,56px)]">
                @csrf
                @foreach ($byWorkshopVoucher ? [$workshopVoucher] : $amountVouchers as $kept)
                    <input type="hidden" name="vouchers[]" value="{{ $kept->code }}">
                @endforeach
                @if ($byWorkshopVoucher)
                    <input type="hidden" name="payment_method" value="{{ PaymentMethod::Voucher->value }}">
                    <input type="hidden" name="voucher_code" value="{{ $workshopVoucher->code }}">
                @else
                    <input type="hidden" name="expected_total" value="{{ $price * $seats }}" x-bind:value="total">
                    <input type="hidden" name="expected_vouchers" value="{{ $vouchersPaid }}" x-bind:value="vouchersPaid">
                @endif

                <div class="min-w-0 flex-[1_1_480px]">
                    <div class="{{ $glass }}">
                        <h2 class="mb-4 flex items-baseline gap-3 font-serif text-[30px] leading-none font-light"><span aria-hidden="true" class="text-[22px] text-rose italic">i</span>Kto przychodzi</h2>
                        <div class="grid gap-[13px]">
                            <x-shared::field name="name" label="Imię i nazwisko" autocomplete="name" />
                            <x-shared::field name="email" label="E-mail" type="email" autocomplete="email" hint="Tu przyjdzie potwierdzenie zapisu" />
                            <x-shared::field name="phone" label="Telefon" type="tel" autocomplete="tel" hint="Na wypadek zmiany terminu" />
                            @if ($byWorkshopVoucher)
                                <p class="text-[13.5px] text-graphite">Ile miejsc: {{ $seats }} — tyle, na ile jest voucher.</p>
                            @else
                            <div class="min-w-0">
                                <label for="seats" class="mb-1.5 block text-[13.5px] text-graphite">Ile miejsc @if ($workshop['unit_label'])<span class="text-label">({{ $workshop['unit_label'] }})</span>@endif</label>
                                <select id="seats" name="seats" x-model.number="seats" aria-describedby="seats-note"
                                        @error('seats') aria-invalid="true" @enderror
                                        @class([
                                            'w-full min-w-0 rounded-[14px] border bg-white px-4 py-[15px] text-[15px] text-ink focus:border-ink pointer-coarse:text-[16px]',
                                            'border-error' => $errors->has('seats'),
                                            'border-line' => ! $errors->has('seats'),
                                        ])>
                                    @for ($count = 1; $count <= $maxSeats; $count++)
                                        <option value="{{ $count }}" @selected($seats === $count)>{{ $count }}</option>
                                    @endfor
                                </select>
                                @error('seats')
                                    <p id="seats-note" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                @else
                                    <p id="seats-note" class="mt-1.5 text-[12.5px] text-hint">Na ten termin: {{ $date->seatsLeftLabel() }}</p>
                                @enderror
                            </div>
                            @endif
                        </div>
                    </div>

                    @unless ($byWorkshopVoucher)
                    {{-- Vouchers that pay for everything leave nothing to choose (MA-244). --}}
                    <fieldset x-show="due > 0" @if ($due === 0) x-cloak @endif class="{{ $glass }}">
                        <legend class="float-left mb-4 flex w-full items-baseline gap-3 font-serif text-[30px] leading-none font-light"><span aria-hidden="true" class="text-[22px] text-rose italic">ii</span>Płatność</legend>
                        <div class="clear-both grid gap-2.5">
                            @foreach ($paymentMethods as $method)
                                <label class="{{ $radio }}">
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

                    {{-- The code here only with BLIK Level 0; otherwise BLIK is chosen on Przelewy24's page. --}}
                    @if ($blikCode)
                        <div x-show="payment === @js(PaymentMethod::Blik->value) && due > 0" @if ($selectedPayment !== PaymentMethod::Blik->value || $due === 0) x-cloak @endif class="{{ $glass }}">
                            <div class="mb-3.5 flex items-center gap-3">
                                <span class="rounded-full bg-navy px-3 py-[5px] text-[12px] font-medium tracking-[0.06em] text-white">BLIK</span>
                                <label for="blik_code" class="text-[13.5px] text-navy-text">Kod z aplikacji banku</label>
                            </div>
                            <input id="blik_code" name="blik_code" x-bind:value="blik"
                                   x-on:input="blik = $el.value = $el.value.replace(/\D/g, '').slice(0, 6)"
                                   inputmode="numeric" autocomplete="one-time-code" placeholder="••••••" aria-describedby="blik_code-note"
                                   @error('blik_code') aria-invalid="true" @enderror
                                   @class([
                                       'w-full min-w-0 rounded-[18px] border bg-white p-[18px] text-center font-serif text-[36px] leading-none font-light tracking-[0.45em] text-ink tabular-nums transition-[border-color,box-shadow] duration-300 placeholder:text-line-strong focus:border-navy focus:shadow-[0_0_0_4px_rgb(36_65_126/.14)] focus:outline-none',
                                       'border-error' => $errors->has('blik_code'),
                                       'border-navy-line' => ! $errors->has('blik_code'),
                                   ])>
                            @error('blik_code')
                                <p id="blik_code-note" class="mt-2.5 text-center text-[13px] text-error">{{ $message }}</p>
                            @else
                                <p id="blik_code-note" class="mt-2.5 text-center text-[12.5px] text-navy-hint">6 cyfr, ważne 2 minuty</p>
                            @enderror
                        </div>
                    @endif
                    @endunless
                </div>

                <div class="min-w-[260px] flex-[1_1_340px] min-[900px]:sticky min-[900px]:top-24">
                    <div class="glass rounded-[26px] p-[clamp(20px,3vw,30px)]">
                        <h2 class="mb-[18px] font-serif text-[30px] leading-none font-light">Twój zapis</h2>
                        <div class="mb-3.5 border-b border-line pb-3.5">
                            <div class="font-serif text-[19px] leading-[1.2] font-light">{{ $workshop['name'] }}</div>
                            <div class="mt-0.5 text-[12.5px] text-label">{{ $when }}</div>
                            <div class="mt-1 text-[13px] text-muted">{{ Money::format($price) }}@if ($workshop['unit_label']) / {{ $workshop['unit_label'] }}@endif</div>
                        </div>
                        @if ($byWorkshopVoucher)
                            <div class="mb-3.5 flex items-start justify-between gap-3 border-b border-line pb-3.5 text-[14px]">
                                <span class="min-w-0">
                                    <span class="block">Voucher {{ $workshopVoucher->code }}</span>
                                    <span class="block text-[12px] text-label">rezerwuje miejsca: {{ $workshopVoucher->seats }}</span>
                                    <button type="button" x-on:click="send(removeVoucherTo, { remove_voucher: @js($workshopVoucher->code) })" class="-ml-1 px-1 {{ $link }}">Nie używaj vouchera {{ $workshopVoucher->code }}</button>
                                    @error('voucher_code')
                                        <span role="alert" class="mt-1 block text-[13px] text-error">{{ $message }}</span>
                                    @enderror
                                </span>
                            </div>
                            <div class="flex items-baseline justify-between">
                                <span class="text-[14.5px]">Płacisz voucherem</span>
                                <span class="font-serif text-[40px] leading-none font-light tabular-nums">{{ Money::format(0) }}</span>
                            </div>
                        @else
                            @foreach ($voucherUses as $index => $use)
                                <div class="mb-3.5 flex items-start justify-between gap-3 border-b border-line pb-3.5 text-[14px]">
                                    <span class="min-w-0">
                                        <span class="block">Voucher {{ $use['voucher']->code }}</span>
                                        <span class="block text-[12px] text-label" x-text="leftOn({{ $index }})">{{ $use['voucher']->balance_minor === $use['amount'] ? 'cały idzie na ten zapis' : 'zostanie na nim '.Money::format($use['voucher']->balance_minor - $use['amount']) }}</span>
                                        <button type="button" x-on:click="send(removeVoucherTo, { remove_voucher: @js($use['voucher']->code) })" class="-ml-1 px-1 {{ $link }}">Nie używaj vouchera {{ $use['voucher']->code }}</button>
                                    </span>
                                    <span class="whitespace-nowrap tabular-nums" x-text="'− ' + format(split[{{ $index }}])">−&thinsp;{{ Money::format($use['amount']) }}</span>
                                </div>
                            @endforeach
                            @if ($takesVouchers)
                                {{-- One field for any code: rare, so folded away like in the shop's checkout (mellowaura-ux). --}}
                                <details @if ($errors->has('voucher_code')) open @endif class="group mb-3.5">
                                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 text-[13.5px] text-graphite hover:text-ink [&::-webkit-details-marker]:hidden">
                                        <span>Masz kod vouchera?</span>
                                        <span aria-hidden="true" class="text-label transition-transform duration-300 group-open:rotate-45">+</span>
                                    </summary>
                                    <div class="grid gap-2.5 pt-1">
                                        <x-shared::field name="voucher_code" label="Kod z vouchera" hint="Voucher na warsztat albo na kwotę — kilka voucherów dodaj po kolei." x-ref="voucherCode"
                                                         x-on:keydown.enter.prevent="send(addVoucherTo)" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="30"
                                                         placeholder="MA-7KQ2-9XHT" />
                                        <button type="button" x-on:click="send(addVoucherTo)"
                                                class="min-h-11 rounded-full border border-line-strong px-5 text-[13.5px] text-ink transition duration-300 hover:border-ink hover:bg-sand">Użyj vouchera</button>
                                    </div>
                                </details>
                            @endif
                            <div class="flex items-baseline justify-between">
                                <span class="text-[14.5px]">Do zapłaty</span>
                                <span x-text="format(due)" class="font-serif text-[40px] leading-none font-light tabular-nums">{{ Money::format($due) }}</span>
                            </div>
                            <p x-show="vouchersPaid > 0 && due === 0" @if ($vouchersPaid === 0 || $due > 0) x-cloak @endif class="mt-2 text-[13px] text-label">Voucher pokrywa całość — nic nie dopłacasz.</p>
                        @endif

                        @if (session('booking_notice'))
                            <p role="alert" class="mt-4 rounded-[14px] border border-alert-line bg-alert px-3.5 py-3 text-[13.5px] text-alert-text">{{ session('booking_notice') }}</p>
                        @endif

                        <label class="mt-2 -mb-3 flex cursor-pointer items-start gap-2.5 py-3 text-[13.5px] leading-[1.5] text-graphite">
                            <input type="checkbox" name="accept_terms" value="1" @checked(old('accept_terms'))
                                   @error('accept_terms') aria-invalid="true" aria-describedby="accept-terms-error" @enderror
                                   class="mt-[3px] size-4 flex-none accent-ink">
                            <span>Akceptuję @if (Route::has('content.terms'))<a href="{{ route('content.terms') }}" target="_blank" rel="noopener">regulamin</a>@else regulamin @endif</span>
                        </label>
                        @error('accept_terms')
                            <p id="accept-terms-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                        @enderror

                        <button type="submit" class="fill-btn mt-5 min-h-[60px] w-full rounded-full bg-navy p-[17px] text-[15px] font-medium tracking-[0.02em] text-linen transition duration-300 [--fill:var(--color-ink)] hover:bg-ink active:scale-[.97]">
                            @if ($byWorkshopVoucher)
                                Zapisuję się z voucherem
                            @else
                                <span x-show="due > 0" @if ($due === 0) x-cloak @endif>Rezerwuję i płacę <span x-text="format(due)">{{ Money::format($due) }}</span></span>
                                <span x-show="due === 0" @if ($due > 0) x-cloak @endif>Zapisuję się, płaci voucher</span>
                            @endif
                        </button>
                        <p class="mt-3 text-center text-[12px] leading-[1.5] text-hint">
                            <span x-show="due > 0" @if ($byWorkshopVoucher || $due === 0) x-cloak @endif>Trzymam miejsce {{ WorkshopBooking::HOLD_MINUTES }} minut na płatność.</span>
                            @if ($location) Pracownia: {{ $location }}. @endif Dokładny adres podaję po zapisie.
                        </p>
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-shared::layout>
