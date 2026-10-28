{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Przepraszam — tego już nie ma na półce

Ktoś kupił {!! $missing->map(fn ($item) => $item->product_name.($item->variant_label ? ' ('.$item->variant_label.')' : ''))->join(', ') !!} chwilę przed zaksięgowaniem Twojej płatności, więc nie wyślę zamówienia {!! $order->number !!}.

{!! $mailTexts->text('order_unavailable', 'intro', ['kwota' => $order->money($refund)]) !!}

@if ($contactEmail)
{!! $mailTexts->text('order_unavailable', 'outro', ['numer' => $order->number]) !!}
@else
Jeśli wolisz podobną sztukę na zamówienie, napisz do mnie przez stronę sklepu i podaj numer {!! $order->number !!}.
@endif

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
Informacja o zamówieniu {!! $order->number !!} złożonym w sklepie MellowAura.
