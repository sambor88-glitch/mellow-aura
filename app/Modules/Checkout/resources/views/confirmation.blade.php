@use('App\Modules\Shared\Support\DispatchTime')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $t = fn (string $key, array $replace = []) => __('checkout::checkout.'.$key, $replace);
    // The order number stands out and can be selected in one tap.
    $withNumber = fn (string $key) => str_replace(':number', '<strong class="font-medium select-all">'.e($order->number).'</strong>', e($t($key)));
@endphp
<x-shared::layout :title="$t('title')" :noindex="true">
    @if ($purchase)
        {{-- Once per order in this browser, so a reload of the confirmation counts no second purchase. --}}
        <x-consent::analytics-event name="purchase" :params="$purchase" :once="'purchase-'.$order->number" />
    @endif
    {{-- The kiln glow grows from the middle and the ✓ draws itself (mellowaura-design, „Żar potwierdzenia”). --}}
    <div class="relative overflow-hidden">
    @if ($paid)
        <div aria-hidden="true" class="pointer-events-none absolute top-[40%] left-1/2 -mt-[55vmax] -ml-[55vmax] size-[110vmax] animate-[auraGlow_2.4s_var(--ease-clay)_both] rounded-full bg-[radial-gradient(circle,#F2C39A_0%,#E4A58C_20%,rgb(214_163_156/.4)_38%,rgb(214_163_156/0)_62%)] blur-[30px]"></div>
    @endif
    <div class="relative mx-auto max-w-[1000px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-14 pb-24 text-center">
        <div class="pt-10 pb-5">
            @if ($paid)
                <svg aria-hidden="true" viewBox="0 0 108 108" class="mx-auto mb-[26px] size-[108px] fill-none stroke-ink">
                    <circle cx="54" cy="54" r="50" stroke-width="1.5" class="animate-[auraDraw_1.1s_var(--ease-clay)_.3s_forwards] [stroke-dasharray:320] [stroke-dashoffset:320]"/>
                    <path d="M34 56 l14 14 l27 -30" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-[auraDraw_.6s_var(--ease-clay)_1.1s_forwards] [stroke-dasharray:80] [stroke-dashoffset:80]"/>
                </svg>
            @endif
            <h1 class="mb-5 animate-[auraBlurIn_1.2s_var(--ease-clay)_.6s_both] font-serif text-[length:clamp(52px,8vw,120px)] leading-[.92] font-light tracking-[-0.03em] text-balance">
                {{ $t(match (true) { ! $paid => 'thanks_waiting', $parcel => 'thanks_packing', default => 'thanks' }) }}
            </h1>
            <p class="mx-auto mb-8 max-w-[48ch] text-[17px] leading-[1.7] text-lead">
                @if (! $paid)
                    {{-- The money is not in yet, so the page must not say „opłacone”. --}}
                    {!! $withNumber('waiting_text') !!}
                @elseif ($parcel)
                    {!! $withNumber('paid_parcel_text') !!}
                @else
                    {!! $withNumber('paid_text') !!}
                @endif
            </p>
            @includeIf('gifts::checkout.vouchers', ['order' => $order])
            @if ($order->hasShortage())
                <p role="status" class="mx-auto mb-8 max-w-[48ch] rounded-[18px] border border-alert-line bg-alert px-5 py-4 text-[15px] leading-[1.6] text-alert-text">
                    {{ $t('shortage') }}
                </p>
            @endif
            <div class="glass mb-6 inline-flex min-w-[min(380px,100%)] flex-col gap-3.5 rounded-[24px] px-[30px] py-[26px] text-left">
                <div class="flex justify-between gap-[30px] text-[14.5px]"><span class="text-label">{{ $t($paid ? 'paid' : 'to_pay') }}</span><span>{{ $order->money($order->total_gross) }}</span></div>
                @if ($shippingLabel)
                    <div class="flex justify-between gap-[30px] text-[14.5px]"><span class="text-label">{{ $t('delivery') }}</span><span>{{ $shippingLabel }}</span></div>
                @endif
                @if ($parcel && ($dispatch = DispatchTime::label($settings)))
                    <div class="flex justify-between gap-[30px] text-[14.5px]"><span class="text-label">{{ $t('dispatch') }}</span><span>{{ $dispatch }}</span></div>
                @endif
            </div>
            <p class="mb-8 text-[13.5px] text-muted">{{ $t('spam') }}</p>
            <a href="{{ route('home') }}" class="fill-btn inline-flex min-h-[52px] items-center gap-2.5 rounded-full bg-ink px-[30px] text-[14px] font-medium text-linen [--fill:var(--color-rose)] hover:bg-rose hover:text-ink">{{ $t('home') }} <span aria-hidden="true">→</span></a>
        </div>
    </div>
    </div>
</x-shared::layout>
