{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Masz nowy termin warsztatu.

{!! $mailTexts->text('booking_moved', 'intro', $textFill) !!}
Poprzedni termin: {!! $previous !!}. Płatność zostaje ta sama — niczego nie dopłacasz.

@include('workshops::mail.partials.booking-text')

@if ($booking->remindsDayBefore())
Dzień przed warsztatem przypomnę Ci o nim mailem.
@endif
@if ($contactEmail)
{!! $mailTexts->text('booking_moved', 'outro', $textFill) !!}
@else
Masz pytanie albo ten termin Ci nie pasuje? Napisz do mnie przez stronę i podaj numer {!! $order->number !!}.
@endif
@if ($contactPhone)
Możesz też napisać na WhatsAppie: {!! $contactPhone !!}.
@endif

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
Nowy termin warsztatu z zapisu {!! $order->number !!}.
