<x-shared::mail.layout
    :title="'Zamówienie '.$order->number"
    :preheader="'Zapłacone '.$order->money($order->total_gross).'.'.($parcel ? ' Zanim paczka wyjdzie, wyślę Ci jej zdjęcie.' : '')"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">{{ $parcel ? 'Dziękuję. Pakuję.' : 'Dziękuję.' }}</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        Zamówienie <strong style="font-weight: 600; color: #2F2620;">{{ $order->number }}</strong> jest opłacone.
        @if ($parcel)
            Zanim paczka wyjdzie, wyślę Ci jej zdjęcie — każdą sztukę zawijam osobno.
        @endif
    </p>

    @if ($missing->isNotEmpty())
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
            <tr>
                <td style="background: #F7ECE9; border: 1px solid #E0C3BC; border-radius: 4px; padding: 14px 16px; font-size: 14.5px; line-height: 1.6; color: #7A3A2E;">
                    Ktoś kupił ostatnią sztukę chwilę przed zaksięgowaniem Twojej płatności:
                    @foreach ($missing as $item){{ $item->product_name }} ({{ $item->variant_label }}@if ($item->missing_quantity > 1), brakuje {{ $item->missing_quantity }} szt.@endif){{ $loop->last ? '.' : ', ' }}@endforeach
                    Za brakujące sztuki zwrócę Ci {{ $order->money($refund) }} — najpóźniej w ciągu 14 dni. Jeśli wolisz podobną sztukę na zamówienie, odpisz na tego maila.
                </td>
            </tr>
        </table>
    @endif

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Twoje zamówienie</div>
    <x-checkout::mail.items :order="$order" :items="$items" :subtotal="$subtotal" :shipping-label="$shippingLabel" />

    <div style="margin: 26px 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Dostawa</div>
    <p style="margin: 0; color: #2F2620;">{{ $shippingLabel }}</p>
    <p style="margin: 0; color: #5C5043;">{{ $delivery }}</p>
    <p style="margin: 0 0 22px; color: #5C5043;">{{ $order->name }} · {{ $order->phone }}</p>

    @includeIf('gifts::mail.order-vouchers', ['order' => $order])

    <p style="margin: 0 0 4px; color: #5C5043;">
        @if ($contactEmail)
            Masz pytanie o zamówienie? Odpisz na tego maila — trafi prosto do mnie.
        @else
            Masz pytanie o zamówienie? Napisz do mnie przez stronę sklepu i podaj numer {{ $order->number }}.
        @endif
        @if ($contactPhone)
            Możesz też napisać na WhatsAppie: {{ $contactPhone }}.
        @endif
    </p>
    <p style="margin: 12px 0 0; font-size: 13.5px; color: #5C5043;">
        W załącznikach jest regulamin sklepu{{ $order->terms_version ? ' ('.$order->terms_version.')' : '' }} i wzór formularza odstąpienia od umowy.
        @if (Route::has('withdrawal.create'))
            Odstąpienie zgłosisz najprościej online: <a href="{{ route('withdrawal.create', ['zamowienie' => $order->number]) }}" style="color: #855F3D;">Odstąp od umowy tutaj</a>.
        @endif
    </p>
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">Kasia</p>

    <x-slot:footer>
        MellowAura{{ $city ? ' · '.$city : '' }}<br>
        Potwierdzenie zamówienia {{ $order->number }} złożonego w sklepie MellowAura.
    </x-slot:footer>
</x-shared::mail.layout>
