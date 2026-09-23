{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Odstąpienie od umowy {!! $withdrawal->order_number !!}

Oświadczenie przyszło {!! $withdrawal->submittedAtLabel() !!} przez formularz „Odstąp od umowy tutaj”. Potwierdzenie z treścią i godziną poszło już na adres {!! $withdrawal->email !!}. Odpowiedź na tego maila trafi prosto do tej osoby.
@if ($withdrawal->order === null)

Nie znalazłam zamówienia {!! $withdrawal->order_number !!} złożonego z adresu {!! $withdrawal->email !!}. Sprawdź je ręcznie, zanim odpiszesz — oświadczenie liczy się tak samo.
@endif

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

Pieniądze trzeba zwrócić w ciągu 14 dni od dziś. Możesz z tym poczekać, aż rzeczy wrócą albo dostaniesz dowód ich nadania.

{!! $withdrawal->order ? 'Otwórz zamówienie w panelu: '.route('admin.orders.show', $withdrawal->order) : 'Otwórz odstąpienia w panelu: '.route('admin.withdrawals.index') !!}
