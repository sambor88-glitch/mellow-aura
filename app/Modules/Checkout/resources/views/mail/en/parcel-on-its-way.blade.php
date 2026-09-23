<x-shared::mail.layout
    :title="'Order '.$order->number.' on its way'"
    :preheader="$order->tracking_number ? 'Tracking number: '.$order->tracking_number.'.' : 'Your parcel has left the studio.'"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Your parcel is on its way</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        I’ve sent your order <strong style="font-weight: 600; color: #2F2620;">{{ $order->number }}</strong>.
        @if ($order->shipping_method === 'parcel_locker')
            InPost will text and e-mail you when it’s waiting in the parcel locker.
        @elseif ($order->shipping_method === 'courier')
            The InPost courier will let you know before delivery.
        @endif
    </p>

    @if ($order->tracking_number)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 10px;">
            <tr>
                <td style="background: #2F2620; border-radius: 999px;">
                    <a href="{{ $order->trackingUrl() }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">Track your parcel with InPost</a>
                </td>
            </tr>
        </table>
        <p style="margin: 0 0 22px; font-size: 13.5px; color: #726456;">Tracking number: {{ $order->tracking_number }}</p>
    @endif

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">In the parcel</div>
    <ul style="margin: 0 0 22px; padding: 0 0 0 18px; color: #2F2620;">
        @foreach ($items as $item)
            <li style="margin: 0 0 4px;">{{ $item->product_name }}@if ($item->variant_label), {{ $item->variant_label }}@endif @if ($item->quantity > 1)× {{ $item->quantity }}@endif</li>
        @endforeach
    </ul>

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Delivery</div>
    <p style="margin: 0; color: #2F2620;">{{ $shippingLabel }}</p>
    <p style="margin: 0 0 22px; color: #5C5043;">{{ $delivery }}</p>

    <p style="margin: 0; color: #5C5043;">
        @if ($contactEmail)
            Something wrong with the parcel? Just reply to this e-mail — it comes straight to me.
        @else
            Something wrong with the parcel? Write to me through the shop and give your order number, {{ $order->number }}.
        @endif
        @if ($contactPhone)
            You can also message me on WhatsApp: {{ $contactPhone }}.
        @endif
    </p>
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">Kasia</p>

    <x-slot:footer>
        MellowAura{{ $city ? ' · '.$city : '' }}<br>
        Dispatch notice for order {{ $order->number }} from the MellowAura shop.
    </x-slot:footer>
</x-shared::mail.layout>
