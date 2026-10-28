@php
    $vouchers = \App\Modules\Gifts\Models\Voucher::query()
        ->with(['orderItem', 'latestBooking.order', 'redemptions.order'])
        ->whereIn('order_item_id', $order->items()->select('id'))
        ->orderBy('id')
        ->get();
    $workshops = app(\App\Modules\Workshops\Support\WorkshopTypes::class)->all();
    // The vouchers this order paid with (MA-211), as opposed to the ones it bought.
    $redemptions = $order->voucherRedemptions()->with('voucher')->get();
    $spent = $redemptions->whereIn('status', [\App\Modules\Gifts\Enums\RedemptionStatus::Held, \App\Modules\Gifts\Enums\RedemptionStatus::Used])->sum('amount_minor');
@endphp
@if ($redemptions->isNotEmpty())
    <section id="vouchery-zamowienia" class="{{ $card }} scroll-mt-6">
        <h2 class="mb-2 font-serif text-[23px]">Zapłacone voucherem</h2>
        <p class="mb-2 text-[13.5px] text-label">To nie rabat: voucher to sposób zapłaty, VAT liczy się od produktów jak zwykle.</p>
        <ul class="grid gap-1 text-[14px]">
            @foreach ($redemptions as $redemption)
                <li class="flex flex-wrap justify-between gap-x-3 border-b border-sand-dark py-2 last:border-b-0">
                    <span><span class="font-serif tracking-[0.06em] select-all">{{ $redemption->voucher->code }}</span> · <span class="text-label">{{ $redemption->status->label() }}</span></span>
                    <span @class(['tabular-nums', 'text-label line-through' => $redemption->status === \App\Modules\Gifts\Enums\RedemptionStatus::Returned])>{{ $order->money($redemption->amount_minor) }}</span>
                </li>
            @endforeach
        </ul>
        @if ($spent > 0 && \App\Modules\Admin\Support\PanelAccess::allows(auth()->user(), 'admin.gifts.order-vouchers.restore'))
            <form method="post" action="{{ route('admin.gifts.order-vouchers.restore', $order) }}" x-data="{ asking: false }" class="mt-3 flex flex-wrap items-center gap-2.5">
                @csrf
                @method('PUT')
                <button type="button" x-show="! asking" x-on:click="asking = true" class="min-h-11 rounded-full border border-line-strong px-4 text-[13px] text-ink hover:border-ink hover:bg-sand">Oddaj {{ $order->money($spent) }} na voucher</button>
                <span x-show="asking" x-cloak class="text-[13px] text-graphite">Kwota wróci na saldo, a zamówienie zostaje, jak jest.</span>
                <button x-show="asking" x-cloak class="min-h-11 rounded-full bg-ink px-4 text-[13px] text-linen hover:bg-rose hover:text-ink">Tak, oddaj na voucher</button>
            </form>
        @endif
    </section>
@endif
@if ($vouchers->isNotEmpty())
    <section class="{{ $card }}">
        <h2 class="mb-2 font-serif text-[23px]">Vouchery</h2>
        <p class="mb-2 text-[13.5px] text-label">Klientka dostała je mailem jako PDF.</p>
        @foreach ($vouchers as $voucher)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-sand-dark py-3.5 last:border-b-0">
                <div class="min-w-0">
                    <div class="font-serif text-[18px] tracking-[0.06em] select-all">{{ $voucher->code }}</div>
                    <div class="mt-0.5 text-[13px] text-label">
                        {{ collect([$voucher->orderItem?->product_name, $voucher->recipient_name ? 'dla: '.$voucher->recipient_name : null, $voucher->sender_name ? 'od: '.$voucher->sender_name : null, 'ważny do '.$voucher->valid_until->translatedFormat('j F Y')])->filter()->join(' · ') }}
                    </div>
                    @if ($voucher->dedication)
                        <div class="mt-1.5 text-[13.5px] whitespace-pre-line text-lead [overflow-wrap:anywhere]">„{{ $voucher->dedication }}”</div>
                    @endif
                </div>
                <a href="{{ route('admin.vouchers.pdf', $voucher) }}" class="inline-flex min-h-11 items-center rounded-full border border-line-strong px-4 py-2 text-[13px] text-ink hover:border-ink hover:bg-sand hover:text-ink">Pobierz PDF</a>
                <div class="w-full">
                    @include('gifts::admin.voucher', ['voucher' => $voucher, 'workshops' => $workshops])
                </div>
            </div>
        @endforeach
    </section>
@endif
