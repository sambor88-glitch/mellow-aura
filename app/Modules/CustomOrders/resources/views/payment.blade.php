@use('App\Modules\Checkout\Enums\PaymentMethod')
@use('App\Modules\Shared\Support\Money')
@php
    $amount = Money::format($link->amount);
    $glass = 'glass mb-4 rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]';
    $radio = 'group flex cursor-pointer items-center gap-3.5 rounded-[18px] border border-line bg-cream/60 px-[18px] py-[15px] transition-[border-color,background-color,transform] duration-300 hover:-translate-y-0.5 hover:border-ink has-checked:border-navy has-checked:bg-navy-soft has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
@endphp

<x-shared::layout title="Płatność za zamówienie indywidualne | MellowAura" :noindex="true">
    <div class="mx-auto max-w-[1180px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-12 pb-24">
        <p class="eyebrow mb-[18px]">zamówienie indywidualne &middot; płatność</p>
        <h1 class="mb-4 font-serif text-[length:clamp(40px,5.6vw,80px)] leading-[.96] font-light tracking-[-0.02em] text-balance">{{ $idea->kind->label() }}</h1>

        @if ($state === 'paid')
            <div class="{{ $glass }} max-w-[620px]">
                <h2 class="mb-3 font-serif text-[30px] leading-none font-light">Ta wpłata już jest</h2>
                <p class="text-[15px] leading-[1.65] text-lead">Dziękuję, {{ $idea->name }} — {{ $amount }} doszło. Gdy będzie coś do zapłaty, wyślę nowy link.</p>
            </div>
        @elseif ($state === 'stale')
            <div class="{{ $glass }} max-w-[620px]">
                <h2 class="mb-3 font-serif text-[30px] leading-none font-light">Ten link jest już nieaktualny</h2>
                <p class="mb-5 text-[15px] leading-[1.65] text-lead">Napisz do mnie albo odpisz na maila z linkiem — wyślę nowy.</p>
                <a href="{{ route('content.contact', ['temat' => 'Zamówienie indywidualne']) }}" class="inline-flex min-h-[52px] items-center rounded-full bg-ink px-[30px] text-[14px] text-linen transition duration-300 hover:bg-rose hover:text-ink">Piszę do Kasi</a>
            </div>
        @else
            <p class="mb-10 max-w-[52ch] text-[16.5px] text-lead">{{ $idea->name }}, zaczynam pracę, gdy tylko bank potwierdzi wpłatę.</p>

            <form method="post" action="{{ route('custom-orders.payment.store', $link->token) }}" novalidate data-submit-once
                  x-data="{ payment: @js($selectedPayment), blik: '' }"
                  class="flex flex-wrap items-start gap-[clamp(24px,4vw,56px)]">
                @csrf

                <div class="min-w-0 flex-[1_1_480px]">
                    <fieldset class="{{ $glass }}">
                        <legend class="float-left mb-4 flex w-full items-baseline gap-3 font-serif text-[30px] leading-none font-light"><span aria-hidden="true" class="text-[22px] text-rose italic">i</span>Płatność</legend>
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
                        <div x-show="payment === @js(PaymentMethod::Blik->value)" @if ($selectedPayment !== PaymentMethod::Blik->value) x-cloak @endif class="{{ $glass }}">
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
                </div>

                <div class="min-w-[min(100%,260px)] flex-[1_1_340px] min-[900px]:sticky min-[900px]:top-24">
                    <div class="glass rounded-[26px] p-[clamp(20px,3vw,30px)]">
                        <h2 class="mb-[18px] font-serif text-[30px] leading-none font-light">Twoja wpłata</h2>
                        <div class="mb-3.5 border-b border-line pb-3.5">
                            <div class="font-serif text-[19px] leading-[1.2] font-light">Zamówienie indywidualne: {{ $idea->kind->label() }}</div>
                            @if ($idea->quote !== null && $idea->quote !== $link->amount)
                                <div class="mt-0.5 text-[12.5px] text-label">Wycena {{ Money::format($idea->quote) }}</div>
                            @endif
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span class="text-[14.5px]">Do zapłaty</span>
                            <span class="font-serif text-[40px] leading-none font-light tabular-nums">{{ $amount }}</span>
                        </div>

                        @if (session('payment_notice'))
                            <p role="alert" class="mt-4 rounded-[14px] border border-alert-line bg-alert px-3.5 py-3 text-[13.5px] text-alert-text">{{ session('payment_notice') }}</p>
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
                            Płacę {{ $amount }}
                        </button>
                        <p class="mt-3 text-center text-[12px] leading-[1.5] text-hint">Link jest ważny do {{ $link->expires_at->locale('pl')->translatedFormat('j F') }}.</p>
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-shared::layout>
