<x-shared::mail.layout
    :title="'Order '.$order->number"
    :preheader="'Waiting for your payment of '.$order->money($order->total_gross).'. Your order confirmation comes in a separate e-mail.'"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Thank you for your order</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        Order <strong style="font-weight: 600; color: #2F2620;">{{ $order->number }}</strong> is waiting for your payment.
        Once it arrives, you’ll get a second e-mail confirming I’ve accepted your order. That is when our contract is made.
    </p>

    @if ($bankTransfer)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
            <tr>
                <td style="background: #F7F2EA; border: 1px solid #E2D7C7; border-radius: 4px; padding: 14px 16px; font-size: 14.5px; line-height: 1.7; color: #2F2620;">
                    <div style="margin: 0 0 4px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Bank transfer details</div>
                    Amount: {{ $order->money($order->total_gross) }}<br>
                    Reference: {{ $order->number }}<br>
                    @if ($bankTransfer['account'])
                        Account: {{ $bankTransfer['account'] }}<br>
                    @endif
                    @if ($bankTransfer['recipient'])
                        Recipient: {{ $bankTransfer['recipient'] }}<br>
                    @endif
                    <span style="color: #5C5043;">{{ $parcel ? 'I’ll send your parcel once the transfer arrives.' : 'I’ll send the PDF once the transfer arrives.' }}</span>
                </td>
            </tr>
        </table>
    @endif

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Your order</div>
    <x-checkout::mail.items :order="$order" :items="$items" :subtotal="$subtotal" :shipping-label="$shippingLabel" />

    <div style="margin: 26px 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Delivery and payment</div>
    <p style="margin: 0; color: #2F2620;">{{ $shippingLabel }}</p>
    <p style="margin: 0; color: #5C5043;">{{ $delivery }}</p>
    <p style="margin: 0; color: #5C5043;">{{ $order->name }} · {{ $order->phone }}</p>
    <p style="margin: 0 0 22px; color: #5C5043;">Payment: {{ $order->payment_method->title() }}</p>

    <p style="margin: 0; color: #5C5043;">
        @if ($contactEmail)
            A question about your order? Just reply to this e-mail — it comes straight to me.
        @else
            A question about your order? Write to me through the shop and give your order number, {{ $order->number }}.
        @endif
    </p>
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">Kasia</p>

    <x-slot:footer>
        MellowAura{{ $city ? ' · '.$city : '' }}<br>
        Summary of order {{ $order->number }} placed in the MellowAura shop.
    </x-slot:footer>
</x-shared::mail.layout>
