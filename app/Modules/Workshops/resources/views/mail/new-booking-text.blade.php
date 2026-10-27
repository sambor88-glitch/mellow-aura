{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Nowy zapis na warsztat

{!! $order->number !!} · {!! $voucher ? 'voucher '.$voucher->code.', bez płatności' : 'opłacone: '.$order->money($order->total_gross).', '.$order->payment_method->label() !!}.
Odpowiedź na tego maila trafi prosto do osoby, która się zapisała.

WARSZTAT
{!! $workshop !!}
{!! $date->dayLabel() !!}, {!! $date->timeLabel() !!}
Liczba osób: {!! $booking->seats !!} · na ten termin zostało: {!! $date->seatsLeftLabel() !!}

KTO
{!! $order->name !!} · {!! $order->phone !!} · {!! $order->email !!}
@if ($order->invoice_nip)
Faktura na NIP {!! $order->invoice_nip !!}
@endif
@if ($order->note)
Dopisek: {!! $order->note !!}
@endif

Zapis w panelu: {!! route('admin.orders.show', $order) !!}
