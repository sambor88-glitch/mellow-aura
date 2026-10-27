{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Można zaczynać

{!! $idea->name !!}, zaczynam pracę, gdy tylko przyjdzie wpłata. Zapłacisz BLIK-iem, kartą albo przelewem przez Przelewy24.

Co to ma być: {!! $idea->kind->label() !!}
@if ($quote)
Wycena: {!! $quote !!}
@endif
Do zapłaty teraz: {!! $amount !!}

Płacę: {!! $url !!}
Link jest ważny do {!! $validUntil !!}.
@if ($contactEmail)

Masz pytanie przed zapłatą? Odpisz na tego maila — trafi prosto do mnie.
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Link do płatności za zamówienie indywidualne.
