@php
    $vouchers = \App\Modules\Gifts\Models\Voucher::query()
        ->whereIn('order_item_id', $order->items()->select('id'))
        ->orderBy('id')
        ->get();
@endphp
@if ($vouchers->isNotEmpty())

{{ $vouchers->count() === 1 ? 'VOUCHER' : 'VOUCHERY' }}
@foreach ($vouchers as $voucher)
{!! $voucher->code !!}{!! $voucher->recipient_name ? ' · dla: '.$voucher->recipient_name : '' !!}{!! $voucher->sender_name ? ' · od: '.$voucher->sender_name : '' !!} · ważny do {!! $voucher->valid_until->translatedFormat('j F Y') !!}
@endforeach
{{ $vouchers->count() === 1 ? 'PDF vouchera wysłałam osobnym mailem, bez cen — możesz go przekazać dalej.' : 'PDF-y voucherów wysłałam osobnym mailem, bez cen — możesz je przekazać dalej.' }}
@endif
