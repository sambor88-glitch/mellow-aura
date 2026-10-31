@use('App\Modules\Shared\Support\DispatchTime')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $t = fn (string $key, array $replace = []) => __('checkout::checkout.'.$key, $replace);
    // The order number stands out and can be selected in one tap.
    $withNumber = fn (string $key) => str_replace(':number', '<strong class="font-medium select-all">'.e($order->number).'</strong>', e($t($key)));
@endphp
<x-shared::layout :title="$t('title')" :noindex="true">
    @if ($purchase && $own)
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
                {{ $t(match (true) { ! $paid && $byTransfer => 'thanks_waiting', ! $paid => 'thanks_confirming', $parcel => 'thanks_packing', default => 'thanks' }) }}
            </h1>
            <p class="mx-auto mb-8 max-w-[48ch] text-[17px] leading-[1.7] text-lead">
                @if (! $paid && $byTransfer)
                    {{-- The money is not in yet, so the page must not say „opłacone”. --}}
                    {!! $withNumber('waiting_text') !!}
                @elseif (! $paid)
                    {{-- Back from Przelewy24's page before its notification: no transfer to make, only a moment to wait. --}}
                    {!! $withNumber('confirming_text') !!}
                    {{-- The same address again: the signed link from Przelewy24 or the session's own. --}}
                    <a href="{{ request()->fullUrl() }}" class="mt-2 block text-graphite underline underline-offset-4 hover:text-ink">{{ $t('check_again') }}</a>
                @elseif ($parcel)
                    {!! $withNumber('paid_parcel_text') !!}
                @else
                    {!! $withNumber('paid_text') !!}
                @endif
            </p>
            @if ($resumeUrl)
                {{-- Przelewy24 says nothing about a cancelled payment, so the way back to it stays here (MA-219). --}}
                <div class="mx-auto mb-8 max-w-[48ch]">
                    @if (session('checkout_notice'))
                        <p role="alert" class="mb-4 rounded-[18px] border border-alert-line bg-alert px-5 py-4 text-[15px] leading-[1.6] text-alert-text">{{ session('checkout_notice') }}</p>
                    @endif
                    <p class="mb-4 text-[15px] leading-[1.6] text-lead">{{ $t($own ? 'resume_text' : 'resume_text_elsewhere') }}</p>
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <form method="POST" action="{{ $resumeUrl }}">
                            @csrf
                            <x-shared::button>{{ $t('resume') }}</x-shared::button>
                        </form>
                        @if ($own)
                            <x-shared::button href="{{ route('checkout.index') }}" variant="secondary">{{ $t('change_payment') }}</x-shared::button>
                        @endif
                    </div>
                </div>
            @endif
            {{-- Without the session (a signed link opened in another browser) the page shows no voucher codes and no locker. --}}
            @if ($own)
                @includeIf('gifts::checkout.vouchers', ['order' => $order])
            @endif
            @if ($order->hasShortage())
                <p role="status" class="mx-auto mb-8 max-w-[48ch] rounded-[18px] border border-alert-line bg-alert px-5 py-4 text-[15px] leading-[1.6] text-alert-text">
                    {{ $t('shortage') }}
                </p>
            @endif
            @if (! $paid && $byTransfer)
                {{-- The same details as the mail (OrderSummary), so nobody has to switch to the inbox to pay. Each one copies with a tap. --}}
                @php
                    $due = $order->amountDue();
                    $account = $settings->get('company_bank_account');
                    $transfer = collect([
                        'transfer_amount' => [$order->money($due), number_format($due / 100, 2, ',', '')],
                        'transfer_title' => [$order->number, $order->number],
                        'transfer_account' => $account ? [$account, str_replace(' ', '', $account)] : null,
                        'transfer_recipient' => ($recipient = $settings->get('company_name')) ? [$recipient, $recipient] : null,
                    ])->filter();
                @endphp
                <section aria-labelledby="transfer-title" class="glass mx-auto mb-6 w-full max-w-[480px] rounded-[24px] px-[clamp(20px,4vw,30px)] py-[26px] text-left">
                    <h2 id="transfer-title" class="eyebrow mb-4">{{ $t('transfer_details') }}</h2>
                    <dl class="grid gap-3">
                        @foreach ($transfer as $key => [$shown, $copy])
                            <div x-data="{ copied: false }" class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line pb-3 last:border-b-0 last:pb-0">
                                <dt class="w-full text-[12.5px] text-label">{{ $t($key) }}</dt>
                                <dd class="min-w-0 flex-1 text-[15.5px] [overflow-wrap:anywhere] tabular-nums select-all">{{ $shown }}</dd>
                                <dd class="flex-none">
                                    <button type="button" x-on:click="navigator.clipboard && navigator.clipboard.writeText(@js($copy)).then(() => { copied = true; setTimeout(() => copied = false, 2000) }, () => {})"
                                            aria-label="{{ $t('copy_label', ['field' => $t($key)]) }}"
                                            class="min-h-11 rounded-full border border-line-strong px-4 text-[13px] text-ink transition-colors duration-300 hover:border-ink hover:bg-sand-dark">
                                        <span x-text="copied ? @js($t('copied')) : @js($t('copy'))">{{ $t('copy') }}</span>
                                    </button>
                                    <span x-text="copied ? @js($t('copied')) : ''" role="status" class="sr-only"></span>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
            <div class="glass mb-6 inline-flex min-w-[min(380px,100%)] flex-col gap-3.5 rounded-[24px] px-[30px] py-[26px] text-left">
                <div class="flex justify-between gap-[30px] text-[14.5px]"><span class="text-label">{{ $t($paid ? 'paid' : 'to_pay') }}</span><span>{{ $order->money($paid ? $order->total_gross : $order->amountDue()) }}</span></div>
                @if ($order->vouchers_gross > 0)
                    {{-- Amount vouchers paid this part (MA-211). --}}
                    <div class="flex justify-between gap-[30px] text-[14.5px]"><span class="text-label">{{ $t('paid_by_voucher') }}</span><span>{{ $order->money($order->vouchers_gross) }}</span></div>
                @endif
                @if ($shippingLabel)
                    <div class="flex justify-between gap-[30px] text-[14.5px]"><span class="text-label">{{ $t('delivery') }}</span><span>{{ $shippingLabel }}</span></div>
                @endif
                {{-- The locker the customer picked, so a typo can still be caught (MA-72). --}}
                @if ($own && $order->shipping_method === 'parcel_locker' && $order->locker_code)
                    <div class="flex justify-between gap-[30px] text-[14.5px]"><span class="text-label">{{ $t('locker') }}</span><span class="select-all">{{ $order->locker_code }}</span></div>
                @endif
                @if ($parcel && ($dispatch = DispatchTime::label($settings)))
                    <div class="flex justify-between gap-[30px] text-[14.5px]"><span class="text-label">{{ $t('dispatch') }}</span><span>{{ $dispatch }}</span></div>
                @endif
            </div>
            <p class="mb-8 text-[13.5px] text-muted">{{ $t('spam') }}</p>
            <x-shared::button href="{{ route('home') }}">{{ $t('home') }} <span aria-hidden="true">→</span></x-shared::button>
        </div>
    </div>
    </div>
</x-shared::layout>
