<x-shared::mail.layout
    :title="'Order '.$order->number"
    :preheader="'Paid '.$order->money($order->total_gross).'.'.($parcel ? ' Before your parcel leaves, I’ll send you a photo of it.' : '')"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">{{ $parcel ? 'Thank you. I’m packing.' : 'Thank you.' }}</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        Order <strong style="font-weight: 600; color: #2F2620;">{{ $order->number }}</strong> is paid.
        @if ($parcel)
            Before your parcel leaves, I’ll send you a photo of it — I wrap every piece on its own.
        @endif
    </p>

    @if ($missing->isNotEmpty())
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
            <tr>
                <td style="background: #F7ECE9; border: 1px solid #E0C3BC; border-radius: 4px; padding: 14px 16px; font-size: 14.5px; line-height: 1.6; color: #7A3A2E;">
                    Someone bought the last piece just before your payment came in:
                    @foreach ($missing as $item){{ $item->product_name }} ({{ $item->variant_label }}@if ($item->missing_quantity > 1), {{ $item->missing_quantity.' missing' }}@endif){{ $loop->last ? '.' : ', ' }}@endforeach
                    I’ll refund {{ $order->money($refund) }} for the missing pieces within 14 days at the latest. If you’d rather have a similar piece made to order, just reply to this e-mail.
                </td>
            </tr>
        </table>
    @endif

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Your order</div>
    <x-checkout::mail.items :order="$order" :items="$items" :subtotal="$subtotal" :shipping-label="$shippingLabel" />

    <div style="margin: 26px 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Delivery</div>
    <p style="margin: 0; color: #2F2620;">{{ $shippingLabel }}</p>
    <p style="margin: 0; color: #5C5043;">{{ $delivery }}</p>
    <p style="margin: 0 0 22px; color: #5C5043;">{{ $order->name }} · {{ $order->phone }}</p>

    <p style="margin: 0 0 4px; color: #5C5043;">
        @if ($contactEmail)
            A question about your order? Just reply to this e-mail — it comes straight to me.
        @else
            A question about your order? Write to me through the shop and give your order number, {{ $order->number }}.
        @endif
        @if ($contactPhone)
            You can also message me on WhatsApp: {{ $contactPhone }}.
        @endif
    </p>
    <p style="margin: 12px 0 0; font-size: 13.5px; color: #5C5043;">
        Attached are the shop terms{{ $order->terms_version ? ' ('.$order->terms_version.')' : '' }} and the model withdrawal form.
        @if (Route::has('withdrawal.create'))
            The easiest way to withdraw is online: <a href="{{ route('withdrawal.create', ['zamowienie' => $order->number]) }}" style="color: #855F3D;">withdraw from the contract here</a>.
        @endif
    </p>
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">Kasia</p>

    <x-slot:footer>
        MellowAura{{ $city ? ' · '.$city : '' }}<br>
        Confirmation of order {{ $order->number }} placed in the MellowAura shop.
    </x-slot:footer>
</x-shared::mail.layout>
