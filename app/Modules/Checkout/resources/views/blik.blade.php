@use('App\Modules\Checkout\Http\Controllers\BlikController')
@use('App\Modules\Checkout\Enums\PaymentStatus')
@php
    $t = fn (string $key, array $replace = []) => __('checkout::blik.'.$key, $replace);
    // The order number stands out and can be selected in one tap, as on the confirmation.
    $withNumber = fn (string $key) => str_replace(':number', '<strong class="font-medium select-all">'.e($order->number).'</strong>', e($t($key)));
    $waiting = $secondsLeft > 0;
    $refused = $order->payment_status === PaymentStatus::Failed || $errors->has('blik_code');
    $glass = 'glass rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]';
@endphp
<x-shared::layout :title="$t('title')" :noindex="true">
    @if ($waiting)
        {{-- Without JavaScript the page asks the server again on its own; the server knows when the money is in. --}}
        <x-slot:head><noscript><meta http-equiv="refresh" content="5"></noscript></x-slot:head>
    @endif
    <div class="mx-auto max-w-[620px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-12 pb-24">
        <p class="eyebrow mb-[18px]">{!! $t('eyebrow') !!}</p>

        @if ($waiting)
            <div x-data="blikWait(@js(['seconds' => $secondsLeft, 'total' => BlikController::SECONDS, 'statusUrl' => route('checkout.blik.status'), 'texts' => ['left' => $t('time_left')]]))">
                <h1 class="mb-4 font-serif text-[length:clamp(40px,6vw,72px)] leading-[.96] font-light tracking-[-0.02em] text-balance">{{ $t('wait_heading') }}</h1>
                <p class="mb-8 max-w-[48ch] text-[16.5px] leading-[1.7] text-lead">{!! $withNumber('wait_text') !!}</p>

                <div class="{{ $glass }} text-center">
                    <div class="mb-4 flex items-center justify-center gap-3">
                        <span class="rounded-full bg-navy px-3 py-[5px] text-[12px] font-medium tracking-[0.06em] text-white">BLIK</span>
                        <span class="text-[13.5px] text-navy-text">{{ $t('checking') }}</span>
                    </div>
                    {{-- The clock is for the eyes; a screen reader hears the time left every half minute instead of every second. --}}
                    <p aria-hidden="true" class="font-serif text-[64px] leading-none font-light text-ink tabular-nums" x-text="clock">{{ intdiv($secondsLeft, 60) }}:{{ str_pad((string) ($secondsLeft % 60), 2, '0', STR_PAD_LEFT) }}</p>
                    <div aria-hidden="true" class="mx-auto mt-5 h-1 max-w-[280px] overflow-hidden rounded-full bg-navy-soft">
                        <div class="h-full rounded-full bg-navy transition-[width] duration-1000 ease-linear" style="width: {{ round($secondsLeft / BlikController::SECONDS * 100) }}%" x-bind:style="{ width: progress + '%' }"></div>
                    </div>
                    <p class="sr-only" role="status" x-text="announced">{{ $t('time_left', ['time' => intdiv($secondsLeft, 60).':'.str_pad((string) ($secondsLeft % 60), 2, '0', STR_PAD_LEFT)]) }}</p>
                    <noscript><p class="mt-4 text-[12.5px] text-navy-hint">{{ $t('noscript') }}</p></noscript>
                </div>
            </div>
        @else
            <h1 class="mb-4 font-serif text-[length:clamp(40px,6vw,72px)] leading-[.96] font-light tracking-[-0.02em] text-balance">{{ $t($refused ? 'refused_heading' : 'expired_heading') }}</h1>
            <p class="mb-8 max-w-[48ch] text-[16.5px] leading-[1.7] text-lead">{!! $withNumber($refused ? 'refused_text' : 'expired_text') !!}</p>

            <form method="post" action="{{ route('checkout.blik.retry') }}" novalidate x-data="{ code: '' }" class="{{ $glass }}">
                @csrf
                <div class="mb-3.5 flex items-center gap-3">
                    <span class="rounded-full bg-navy px-3 py-[5px] text-[12px] font-medium tracking-[0.06em] text-white">BLIK</span>
                    <label for="blik_code" class="text-[13.5px] text-navy-text">{{ $t('label') }}</label>
                </div>
                <input id="blik_code" name="blik_code" x-bind:value="code"
                       x-on:input="code = $el.value = $el.value.replace(/\D/g, '').slice(0, 6)"
                       inputmode="numeric" autocomplete="one-time-code" placeholder="• • • • • •" aria-describedby="blik_code-note" autofocus
                       @error('blik_code') aria-invalid="true" @enderror
                       @class([
                           'w-full min-w-0 rounded-[18px] border bg-white p-[18px] text-center font-serif text-[36px] leading-none font-light tracking-[0.45em] text-ink tabular-nums transition-[border-color,box-shadow] duration-300 placeholder:text-line-strong focus:border-navy focus:shadow-[0_0_0_4px_rgb(36_65_126/.14)] focus:outline-none',
                           'border-error' => $errors->has('blik_code'),
                           'border-navy-line' => ! $errors->has('blik_code'),
                       ])>
                @error('blik_code')
                    <p id="blik_code-note" class="mt-2.5 text-center text-[13px] text-error">{{ $message }}</p>
                @else
                    <p id="blik_code-note" class="mt-2.5 text-center text-[12.5px] text-navy-hint">{{ $t('hint') }}</p>
                @enderror
                <button type="submit" class="fill-btn mt-5 min-h-[60px] w-full rounded-full bg-navy p-[17px] text-[15px] font-medium tracking-[0.02em] text-linen transition duration-300 [--fill:var(--color-ink)] hover:bg-ink active:scale-[.97]">
                    {{ $t('pay', ['amount' => $order->money($order->total_gross)]) }}
                </button>
            </form>
            {{-- Back to the checkout with the cart as it was: another way to pay is a new order there. --}}
            <p class="mt-6 text-center text-[14px]"><a href="{{ route('checkout.index') }}" class="text-graphite underline underline-offset-4 hover:text-ink">{{ $t('other') }}</a></p>
        @endif
    </div>
</x-shared::layout>
