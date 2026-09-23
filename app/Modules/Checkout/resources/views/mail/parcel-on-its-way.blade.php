<x-shared::mail.layout
    :title="'Zamówienie '.$order->number.' w drodze'"
    :preheader="$order->tracking_number ? 'Numer przesyłki: '.$order->tracking_number.'.' : 'Paczka wyszła z pracowni.'"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Paczka w drodze</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        Wysłałam Twoje zamówienie <strong style="font-weight: 600; color: #2F2620;">{{ $order->number }}</strong>.
        @if ($order->shipping_method === 'parcel_locker')
            InPost napisze do Ciebie SMS-a i maila, gdy paczka będzie czekać w paczkomacie.
        @elseif ($order->shipping_method === 'courier')
            Kurier InPost da znać przed doręczeniem.
        @endif
    </p>

    @if ($order->tracking_number)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 10px;">
            <tr>
                <td style="background: #2F2620; border-radius: 999px;">
                    <a href="{{ $order->trackingUrl() }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">Śledź paczkę na stronie InPost</a>
                </td>
            </tr>
        </table>
        <p style="margin: 0 0 22px; font-size: 13.5px; color: #726456;">Numer przesyłki: {{ $order->tracking_number }}</p>
    @endif

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">W paczce</div>
    <ul style="margin: 0 0 22px; padding: 0 0 0 18px; color: #2F2620;">
        @foreach ($items as $item)
            <li style="margin: 0 0 4px;">{{ $item->product_name }}@if ($item->variant_label), {{ $item->variant_label }}@endif @if ($item->quantity > 1)× {{ $item->quantity }}@endif</li>
        @endforeach
    </ul>

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Dostawa</div>
    <p style="margin: 0; color: #2F2620;">{{ $shippingLabel }}</p>
    <p style="margin: 0 0 22px; color: #5C5043;">{{ $delivery }}</p>

    <p style="margin: 0; color: #5C5043;">
        @if ($contactEmail)
            Coś nie tak z paczką? Odpisz na tego maila — trafi prosto do mnie.
        @else
            Coś nie tak z paczką? Napisz do mnie przez stronę sklepu i podaj numer {{ $order->number }}.
        @endif
        @if ($contactPhone)
            Możesz też napisać na WhatsAppie: {{ $contactPhone }}.
        @endif
    </p>
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">Kasia</p>

    <x-slot:footer>
        MellowAura{{ $city ? ' · '.$city : '' }}<br>
        Wiadomość o wysłaniu zamówienia {{ $order->number }} ze sklepu MellowAura.
    </x-slot:footer>
</x-shared::mail.layout>
