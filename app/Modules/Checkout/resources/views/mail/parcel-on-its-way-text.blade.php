{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Paczka w drodze

Wysłałam Twoje zamówienie {!! $order->number !!}.{!! match ($order->shipping_method) {
    'parcel_locker' => ' InPost napisze do Ciebie SMS-a i maila, gdy paczka będzie czekać w paczkomacie.',
    'courier' => ' Kurier InPost da znać przed doręczeniem.',
    default => '',
} !!}
@if ($order->tracking_number)

Numer przesyłki: {!! $order->tracking_number !!}
Śledź paczkę na stronie InPost: {!! $order->trackingUrl() !!}
@endif

W PACZCE
@foreach ($items as $item)
- {!! $item->product_name.($item->variant_label ? ', '.$item->variant_label : '').($item->quantity > 1 ? ' × '.$item->quantity : '') !!}
@endforeach

DOSTAWA
{!! $shippingLabel !!}
{!! $delivery !!}

@if ($contactEmail)
Coś nie tak z paczką? Odpisz na tego maila — trafi prosto do mnie.
@else
Coś nie tak z paczką? Napisz do mnie przez stronę sklepu i podaj numer {!! $order->number !!}.
@endif
@if ($contactPhone)
Możesz też napisać na WhatsAppie: {!! $contactPhone !!}.
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Wiadomość o wysłaniu zamówienia {!! $order->number !!} ze sklepu MellowAura.
