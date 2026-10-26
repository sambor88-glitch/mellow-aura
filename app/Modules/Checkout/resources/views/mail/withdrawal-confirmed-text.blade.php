{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Dostałam Twoje oświadczenie

Potwierdzam, że oświadczenie o odstąpieniu od umowy doszło do mnie {!! $withdrawal->submittedAtLabel() !!}. Zachowaj tego maila — potwierdza datę i godzinę wysłania oświadczenia.

TREŚĆ OŚWIADCZENIA
{!! $withdrawal->statement() !!}

Imię i nazwisko: {!! $withdrawal->name !!}
E-mail: {!! $withdrawal->email !!}
Numer zamówienia: {!! $withdrawal->order_number !!}
Zakres: {!! $withdrawal->scope->label() !!}
@if ($withdrawal->items !== null)
Rzeczy: {!! $withdrawal->items !!}
@endif
Wysłane: {!! $withdrawal->submittedAtLabel() !!}

CO DALEJ
Rzeczy odeślij w ciągu 14 dni od dziś{!! $returnAddress ? ' na adres: '.$returnAddress : '' !!}. Koszt odesłania jest po Twojej stronie. Ceramikę zapakuj tak, żeby nie stłukła się w drodze.

Pieniądze zwrócę w ciągu 14 dni od dziś, tą samą metodą płatności. Mogę z tym poczekać, aż rzeczy do mnie wrócą albo dostanę dowód ich nadania. Szczegóły są w regulaminie, w §12: {!! route('content.terms') !!}#regulamin-12-prawo-odstapienia-od-umowy
@if ($contactEmail)

Masz pytanie? Odpisz na tego maila — trafi prosto do mnie.
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Potwierdzenie odstąpienia od umowy wysłane przez formularz „Odstąp od umowy tutaj” w sklepie MellowAura.
