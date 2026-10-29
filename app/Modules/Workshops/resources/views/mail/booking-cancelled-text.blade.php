{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
@php
    // An amount voucher's part is already back on its balance (CancelBooking); only the rest is money to return (MA-244).
    $refund = $order->amountDue() > 0 ? 'Zwrócę Ci '.($order->vouchers_gross > 0 ? '' : 'całą ').'wpłatę, '.$order->money($order->amountDue()).', najpóźniej w ciągu 14 dni, na konto, z którego przyszła.' : null;
    $backOnVoucher = $order->vouchers_gross > 0 ? 'Kwota z vouchera, '.$order->money($order->vouchers_gross).', wróciła już na voucher — wykorzystasz ją w sklepie albo przy kolejnym zapisie.' : null;
@endphp
Twój zapis jest anulowany.

{!! $mailTexts->text('booking_cancelled', 'intro', $textFill) !!}

@if ($voucher && ! $voucher['expired'])
Twój voucher {!! $voucher['code'] !!} znów jest ważny — do {!! $voucher['valid_until'] !!}. Zapiszesz się nim na inny termin na stronie. Jeśli wolisz, odpisz na tego maila — przepiszę Cię sama.
@elseif ($voucher)
Twój voucher {!! $voucher['code'] !!} wraca do Ciebie. Jego ważność minęła {!! $voucher['valid_until'] !!} — odpisz na tego maila, a przedłużę go i zapiszę Cię na inny termin.
@else
{!! collect([$refund, $backOnVoucher])->filter()->join(' ') !!} Jeśli wolisz inny termin, odpisz na tego maila — przepiszę Cię zamiast zwrotu.
@endif
@if ($contactPhone)
Możesz też napisać na WhatsAppie: {!! $contactPhone !!}.
@endif

Plik w załączniku usunie warsztat z Twojego kalendarza.

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
Wiadomość o zapisie na warsztat {!! $order->number !!}.
