{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Dziękuję za zamówienie

{!! $mailTexts->text('order_awaiting_payment', 'intro', ['numer' => $order->number]) !!} Gdy wpłata dotrze, dostaniesz drugi mail — potwierdzenie przyjęcia zamówienia do realizacji. Z tą chwilą zawieramy umowę.
@if ($bankTransfer)

DANE DO PRZELEWU
Kwota: {{ $order->money($order->amountDue()) }}
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
{!! $mailTexts->text('order_awaiting_payment', 'outro', ['numer' => $order->number]) !!}
@else
Masz pytanie o zamówienie? Napisz do mnie przez stronę sklepu i podaj numer {!! $order->number !!}.
@endif

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
Podsumowanie zamówienia {!! $order->number !!} złożonego w sklepie MellowAura.
