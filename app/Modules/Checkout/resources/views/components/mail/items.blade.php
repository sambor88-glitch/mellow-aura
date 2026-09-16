@use('App\Modules\Shared\Support\Money')
@props(['order', 'items', 'subtotal', 'shippingLabel'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
    @foreach ($items as $item)
        <tr>
            <td style="padding: 12px 0; border-top: 1px solid #E2D7C7; vertical-align: top;">
                <div style="font-size: 15px; color: #2F2620;">{{ $item->product_name }}</div>
                <div style="font-size: 13px; color: #726456;">{{ $item->variant_label }}@if ($item->quantity > 1) · {{ $item->quantity }} × {{ Money::format($item->unit_price_gross) }}@endif</div>
                @if ($item->custom_text)
                    <div style="font-size: 13px; color: #5C5043;">Napis: „{{ str_replace("\n", ' / ', $item->custom_text) }}”</div>
                @endif
                @if ($item->custom_glaze)
                    <div style="font-size: 13px; color: #5C5043;">Kolor wnętrza: {{ $item->custom_glaze }}</div>
                @endif
            </td>
            <td align="right" style="padding: 12px 0 12px 16px; border-top: 1px solid #E2D7C7; vertical-align: top; white-space: nowrap; font-size: 15px; color: #2F2620;">{{ Money::format($item->total()) }}</td>
        </tr>
    @endforeach
    <tr>
        <td style="padding: 12px 0 2px; border-top: 1px solid #E2D7C7; font-size: 14px; color: #726456;">Produkty</td>
        <td align="right" style="padding: 12px 0 2px 16px; border-top: 1px solid #E2D7C7; white-space: nowrap; font-size: 14px; color: #2F2620;">{{ Money::format($subtotal) }}</td>
    </tr>
    <tr>
        <td style="padding: 2px 0; font-size: 14px; color: #726456;">Dostawa · {{ $shippingLabel }}</td>
        <td align="right" style="padding: 2px 0 2px 16px; white-space: nowrap; font-size: 14px; color: #2F2620;">{{ $order->shipping_gross > 0 ? Money::format($order->shipping_gross) : 'gratis' }}</td>
    </tr>
    <tr>
        <td style="padding: 10px 0 0; font-size: 16px; color: #2F2620;"><strong>Razem</strong></td>
        <td align="right" style="padding: 10px 0 0 16px; white-space: nowrap; font-family: Georgia, 'Times New Roman', serif; font-size: 20px; color: #2F2620;">{{ Money::format($order->total_gross) }}</td>
    </tr>
</table>
