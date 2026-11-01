{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Jak się u Ciebie sprawdza?

{!! $mailTexts->text('review_request', 'intro', ['numer' => $order->number]) !!}

Jeśli masz minutę, napisz kilka zdań o zakupie na mojej wizytówce Google. Opinia pomaga innym zdecydować, a mnie mówi, co robię dobrze, a co mogę poprawić.

Napisz opinię w Google: {!! $reviewUrl !!}

Szczera opinia jest dla mnie najcenniejsza — także krytyczna.

@if ($contactEmail)
{!! $mailTexts->text('review_request', 'outro', ['numer' => $order->number]) !!}
@else
Coś jest nie tak z zamówieniem? Napisz do mnie przez stronę sklepu i podaj numer {!! $order->number !!}.
@endif

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
Jednorazowa prośba o opinię o zamówieniu {!! $order->number !!} ze sklepu MellowAura.
