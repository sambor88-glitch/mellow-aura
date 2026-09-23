@php
    $vouchers = \App\Modules\Gifts\Models\Voucher::query()
        ->whereIn('order_item_id', $order->items()->select('id'))
        ->orderBy('id')
        ->get();
@endphp
@if ($vouchers->isNotEmpty())
    {{-- The order confirmation names the codes; the PDFs go out in a separate mail without prices. --}}
    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">{{ $vouchers->count() === 1 ? 'Voucher' : 'Vouchery' }}</div>
    @foreach ($vouchers as $voucher)
        <p style="margin: 0; color: #2F2620;">
            <strong style="font-weight: 600; letter-spacing: 1px;">{{ $voucher->code }}</strong>{{ $voucher->recipient_name ? ' · dla: '.$voucher->recipient_name : '' }}{{ $voucher->sender_name ? ' · od: '.$voucher->sender_name : '' }} · ważny do {{ $voucher->valid_until->translatedFormat('j F Y') }}
        </p>
    @endforeach
    <p style="margin: 4px 0 22px; color: #5C5043;">{{ $vouchers->count() === 1 ? 'PDF vouchera wysłałam osobnym mailem, bez cen — możesz go przekazać dalej.' : 'PDF-y voucherów wysłałam osobnym mailem, bez cen — możesz je przekazać dalej.' }}</p>
@endif
