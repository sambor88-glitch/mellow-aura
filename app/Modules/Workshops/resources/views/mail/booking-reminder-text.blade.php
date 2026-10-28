{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Do jutra!

{!! $mailTexts->text('booking_reminder', 'intro', $textFill) !!}

@include('workshops::mail.partials.booking-text')

@if ($contactEmail)
{!! $mailTexts->text('booking_reminder', 'outro', $textFill) !!}
@else
Coś Ci wypadło albo się spóźnisz? Napisz do mnie przez stronę i podaj numer {!! $order->number !!}.
@endif
@if ($contactPhone)
Najszybciej złapiesz mnie na WhatsAppie: {!! $contactPhone !!}.
@endif

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
Przypomnienie o warsztacie z zapisu {!! $order->number !!}.
