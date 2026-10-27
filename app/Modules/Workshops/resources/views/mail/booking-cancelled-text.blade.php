{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Twój zapis jest anulowany.

Anulowałam Twój zapis na warsztat {!! $date->dayLabel() !!}, {!! $date->timeLabel() !!} (zapis {!! $order->number !!}).

@if ($voucher && ! $voucher['expired'])
Twój voucher {!! $voucher['code'] !!} znów jest ważny — do {!! $voucher['valid_until'] !!}. Zapiszesz się nim na inny termin na stronie. Jeśli wolisz, odpisz na tego maila — przepiszę Cię sama.
@elseif ($voucher)
Twój voucher {!! $voucher['code'] !!} wraca do Ciebie. Jego ważność minęła {!! $voucher['valid_until'] !!} — odpisz na tego maila, a przedłużę go i zapiszę Cię na inny termin.
@else
Zwrócę Ci całą wpłatę, {!! $order->money($order->total_gross) !!}, najpóźniej w ciągu 14 dni, na konto, z którego przyszła. Jeśli wolisz inny termin, odpisz na tego maila — przepiszę Cię zamiast zwrotu.
@endif
@if ($contactPhone)
Możesz też napisać na WhatsAppie: {!! $contactPhone !!}.
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Wiadomość o zapisie na warsztat {!! $order->number !!}.
