@php
    $vouchers = \App\Modules\Gifts\Models\Voucher::query()
        ->whereIn('order_item_id', $order->items()->select('id'))
        ->orderBy('id')
        ->get();
@endphp
@if ($vouchers->isNotEmpty())
    {{-- Signed links for a day: the code alone is worth money. The same PDFs go out in a mail without prices. --}}
    <div class="mx-auto mb-8 max-w-[48ch] rounded-[4px] border border-divider bg-cream px-6 py-5 text-left">
        <p class="mb-3.5 text-[15px] leading-[1.6] text-lead">
            {{ $vouchers->count() === 1
                ? 'Voucher wysłałam Ci osobnym mailem, bez cen — możesz go przekazać dalej. PDF pobierzesz też od razu:'
                : 'Vouchery wysłałam Ci osobnym mailem, bez cen — możesz je przekazać dalej. PDF-y pobierzesz też od razu:' }}
        </p>
        <div class="flex flex-col items-start gap-2.5">
            @foreach ($vouchers as $voucher)
                <a href="{{ URL::temporarySignedRoute('vouchers.pdf', now()->addDay(), ['voucher' => $voucher->code]) }}"
                   class="inline-flex min-h-11 items-center rounded-full border border-line-strong px-5 py-2.5 text-[14px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.98]">
                    Pobierz voucher {{ $voucher->code }}{{ $voucher->recipient_name ? ' dla: '.$voucher->recipient_name : '' }}
                </a>
            @endforeach
        </div>
    </div>
@endif
