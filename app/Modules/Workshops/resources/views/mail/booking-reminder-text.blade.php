{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Do jutra!

Przypominam o warsztacie — glina już czeka. Wszystko jest w tym mailu, nie musisz szukać poprzedniego.

@include('workshops::mail.partials.booking-text')

@if ($contactEmail)
Coś Ci wypadło albo się spóźnisz? Odpisz na tego maila — trafi prosto do mnie.
@else
Coś Ci wypadło albo się spóźnisz? Napisz do mnie przez stronę i podaj numer {!! $order->number !!}.
@endif
@if ($contactPhone)
Najszybciej złapiesz mnie na WhatsAppie: {!! $contactPhone !!}.
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Przypomnienie o warsztacie z zapisu {!! $order->number !!}.
