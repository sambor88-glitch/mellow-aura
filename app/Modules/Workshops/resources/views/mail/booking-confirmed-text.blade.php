{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Dziękuję. Do zobaczenia w pracowni.

{!! $mailTexts->text('booking_confirmed', 'intro', $textFill, $booking->seats > 1 ? 'many' : null) !!}

@include('workshops::mail.partials.booking-text')

@if ($booking->remindsDayBefore())
Dzień przed warsztatem przypomnę Ci o nim mailem.
@endif
@if ($contactEmail)
{!! $mailTexts->text('booking_confirmed', 'outro', $textFill) !!}
@else
Masz pytanie albo coś Ci wypadło? Napisz do mnie przez stronę i podaj numer {!! $order->number !!}.
@endif
@if ($contactPhone)
Możesz też napisać na WhatsAppie: {!! $contactPhone !!}.
@endif

W załączniku jest regulamin{!! $order->terms_version ? ' ('.$order->terms_version.')' : '' !!}.

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
Potwierdzenie zapisu na warsztat {!! $order->number !!}.
