{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Przepraszam — termin się zapełnił.

Twoja płatność za warsztat {!! $date->dayLabel() !!}, {!! $date->timeLabel() !!} (zapis {!! $order->number !!}) dotarła, kiedy ostatnie miejsce zajęła już inna osoba.

Zwrócę Ci całą wpłatę, {!! $order->money($order->total_gross) !!}, najpóźniej w ciągu 14 dni. Jeśli wolisz inny termin, odpisz na tego maila — przepiszę Cię zamiast zwrotu.
@if ($contactPhone)
Możesz też napisać na WhatsAppie: {!! $contactPhone !!}.
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Wiadomość o zapisie na warsztat {!! $order->number !!}.
