@use('App\Modules\Shared\Support\Money')
{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Dziękuję. Pakuję.

Zamówienie {!! $order->number !!} jest opłacone. Zanim paczka wyjdzie, wyślę Ci jej zdjęcie — każdą sztukę zawijam osobno.
@if ($missing->isNotEmpty())

Ktoś kupił ostatnią sztukę chwilę przed Tobą: {!! $missing->map(fn ($item) => $item->product_name.' ('.$item->variant_label.')')->join(', ') !!}. Napiszę do Ciebie, żeby ustalić, co dalej — mogę zrobić kolejną albo oddać pieniądze.
@endif

TWOJE ZAMÓWIENIE
@include('checkout::mail.partials.items-text')

DOSTAWA
{!! $shippingLabel !!}
{!! $delivery !!}
{!! $order->name !!} · {!! $order->phone !!}

@if ($contactEmail)
Masz pytanie o zamówienie? Odpisz na tego maila — trafi prosto do mnie.
@else
Masz pytanie o zamówienie? Napisz do mnie przez stronę sklepu i podaj numer {!! $order->number !!}.
@endif
@if ($contactPhone)
Możesz też napisać na WhatsAppie: {!! $contactPhone !!}.
@endif
@if (Route::has('withdrawal.create'))

Odstąpienie od umowy zgłosisz przez formularz „Odstąp od umowy tutaj”: {!! route('withdrawal.create', ['zamowienie' => $order->number]) !!}
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Potwierdzenie zamówienia {!! $order->number !!} złożonego w sklepie MellowAura.
