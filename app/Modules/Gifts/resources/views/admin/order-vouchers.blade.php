@php
    $vouchers = \App\Modules\Gifts\Models\Voucher::query()
        ->with('orderItem')
        ->whereIn('order_item_id', $order->items()->select('id'))
        ->orderBy('id')
        ->get();
@endphp
@if ($vouchers->isNotEmpty())
    <section class="{{ $card }}">
        <h2 class="mb-2 font-serif text-[23px]">Vouchery</h2>
        <p class="mb-2 text-[13.5px] text-label">Klientka dostała je mailem jako PDF.</p>
        @foreach ($vouchers as $voucher)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-sand-dark py-3.5 last:border-b-0">
                <div class="min-w-0">
                    <div class="font-serif text-[18px] tracking-[0.06em] select-all">{{ $voucher->code }}</div>
                    <div class="mt-0.5 text-[13px] text-label">
                        {{ collect([$voucher->orderItem?->product_name, $voucher->recipient_name ? 'dla: '.$voucher->recipient_name : null, 'ważny do '.$voucher->valid_until->translatedFormat('j F Y')])->filter()->join(' · ') }}
                    </div>
                    @if ($voucher->dedication)
                        <div class="mt-1.5 text-[13.5px] whitespace-pre-line text-lead [overflow-wrap:anywhere]">„{{ $voucher->dedication }}”</div>
                    @endif
                </div>
                <a href="{{ route('admin.vouchers.pdf', $voucher) }}" class="inline-flex min-h-11 items-center rounded-full border border-line-strong px-4 py-2 text-[13px] text-ink hover:border-ink hover:bg-sand hover:text-ink">Pobierz PDF</a>
            </div>
        @endforeach
    </section>
@endif
