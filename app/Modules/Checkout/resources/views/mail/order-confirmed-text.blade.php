{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
{!! $parcel ? 'Dziękuję. Pakuję.' : 'Dziękuję.' !!}

Zamówienie {!! $order->number !!} jest opłacone.{!! $parcel ? ' Zanim paczka wyjdzie, wyślę Ci jej zdjęcie — każdą sztukę zawijam osobno.' : '' !!}
@if ($missing->isNotEmpty())

Ktoś kupił ostatnią sztukę chwilę przed zaksięgowaniem Twojej płatności: {!! $missing->map(fn ($item) => $item->product_name.' ('.$item->variant_label.')')->join(', ') !!}. Za brakujące sztuki zwrócę Ci {{ $order->money($refund) }} — najpóźniej w ciągu 14 dni. Jeśli wolisz podobną sztukę na zamówienie, odpisz na tego maila.
@endif

TWOJE ZAMÓWIENIE
@include('checkout::mail.partials.items-text')

DOSTAWA
{!! $shippingLabel !!}
{!! $delivery !!}
{!! $order->name !!} · {!! $order->phone !!}
@includeIf('gifts::mail.order-vouchers-text', ['order' => $order])

@if ($contactEmail)
Masz pytanie o zamówienie? Odpisz na tego maila — trafi prosto do mnie.
@else
Masz pytanie o zamówienie? Napisz do mnie przez stronę sklepu i podaj numer {!! $order->number !!}.
@endif
@if ($contactPhone)
Możesz też napisać na WhatsAppie: {!! $contactPhone !!}.
@endif

W załącznikach jest regulamin sklepu{!! $order->terms_version ? ' ('.$order->terms_version.')' : '' !!} i wzór formularza odstąpienia od umowy.
@if (Route::has('withdrawal.create'))
Odstąpienie zgłosisz najprościej online, przez formularz „Odstąp od umowy tutaj”: {!! route('withdrawal.create', ['zamowienie' => $order->number]) !!}
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Potwierdzenie zamówienia {!! $order->number !!} złożonego w sklepie MellowAura.
