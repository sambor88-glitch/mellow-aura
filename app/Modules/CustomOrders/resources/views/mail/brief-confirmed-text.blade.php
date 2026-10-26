{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Mam Twój pomysł

Dziękuję, {!! $customOrderRequest->name !!}. Przeczytam wszystko i odpiszę ze szkicem i wyceną. Jeśli czegoś mi zabraknie, dopytam.

@foreach ($customOrderRequest->summary() as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach

TWÓJ OPIS
{!! $customOrderRequest->description !!}
@if ($contactEmail)

Chcesz coś dopisać albo dosłać zdjęcie? Odpisz na tego maila — trafi prosto do mnie.
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Potwierdzenie wysłane przez formularz „Zamówienia indywidualne” na stronie MellowAura.
