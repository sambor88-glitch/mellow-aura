{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Można zaczynać

{!! $mailTexts->text('payment_link', 'intro', ['imie' => $idea->name, 'kwota' => $amount]) !!}

Co to ma być: {!! $idea->kind->label() !!}
@if ($quote)
Wycena: {!! $quote !!}
@endif
Do zapłaty teraz: {!! $amount !!}

Płacę: {!! $url !!}
Link jest ważny do {!! $validUntil !!}.
@if ($contactEmail)

{!! $mailTexts->text('payment_link', 'outro', ['imie' => $idea->name, 'kwota' => $amount]) !!}
@endif

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
Link do płatności za zamówienie indywidualne.
