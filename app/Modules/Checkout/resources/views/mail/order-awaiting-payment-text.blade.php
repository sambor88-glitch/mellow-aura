{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Dziękuję za zamówienie

Zamówienie {!! $order->number !!} czeka na płatność. Gdy wpłata dotrze, dostaniesz drugi mail — potwierdzenie przyjęcia zamówienia do realizacji. Z tą chwilą zawieramy umowę.
@if ($bankTransfer)

DANE DO PRZELEWU
Kwota: {{ $order->money($order->total_gross) }}
Tytuł: {!! $order->number !!}
@if ($bankTransfer['account'])
Rachunek: {!! $bankTransfer['account'] !!}
@endif
@if ($bankTransfer['recipient'])
Odbiorca: {!! $bankTransfer['recipient'] !!}
@endif
{!! $parcel ? 'Paczkę wyślę po zaksięgowaniu przelewu.' : 'PDF wyślę po zaksięgowaniu przelewu.' !!}
@endif

TWOJE ZAMÓWIENIE
@include('checkout::mail.partials.items-text')

DOSTAWA I PŁATNOŚĆ
{!! $shippingLabel !!}
{!! $delivery !!}
{!! $order->name !!} · {!! $order->phone !!}
Płatność: {!! $order->payment_method->label() !!}

@if ($contactEmail)
Masz pytanie o zamówienie? Odpisz na tego maila — trafi prosto do mnie.
@else
Masz pytanie o zamówienie? Napisz do mnie przez stronę sklepu i podaj numer {!! $order->number !!}.
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Podsumowanie zamówienia {!! $order->number !!} złożonego w sklepie MellowAura.
