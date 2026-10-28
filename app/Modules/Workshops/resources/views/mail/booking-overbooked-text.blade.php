{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
@php
    // Only the money that came in goes back to the account; an amount voucher's part goes back to the voucher (MA-244).
    $refund = collect([
        $order->amountDue() > 0 ? 'Zwrócę Ci '.($order->vouchers_gross > 0 ? '' : 'całą ').'wpłatę, '.$order->money($order->amountDue()).', najpóźniej w ciągu 14 dni.' : null,
        $order->vouchers_gross > 0 ? 'Kwotę z vouchera, '.$order->money($order->vouchers_gross).', oddam na voucher.' : null,
    ])->filter()->join(' ');
@endphp
Przepraszam — termin się zapełnił.

{!! $mailTexts->text('booking_overbooked', 'intro', $textFill) !!}

{!! $refund !!} Jeśli wolisz inny termin, odpisz na tego maila — przepiszę Cię zamiast zwrotu.
@if ($contactPhone)
Możesz też napisać na WhatsAppie: {!! $contactPhone !!}.
@endif

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
Wiadomość o zapisie na warsztat {!! $order->number !!}.
