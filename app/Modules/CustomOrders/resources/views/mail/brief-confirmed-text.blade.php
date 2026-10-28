{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Mam Twój pomysł

{!! $mailTexts->text('brief_confirmed', 'intro', ['imie' => $customOrderRequest->name]) !!}

@foreach ($customOrderRequest->summary() as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach

TWÓJ OPIS
{!! $customOrderRequest->description !!}
@if ($contactEmail)

{!! $mailTexts->text('brief_confirmed', 'outro', ['imie' => $customOrderRequest->name]) !!}
@endif

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
Potwierdzenie wysłane przez formularz „Zamówienia indywidualne” na stronie MellowAura.
