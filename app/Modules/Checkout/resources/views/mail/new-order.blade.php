<x-shared::mail.layout
    :title="'Nowe zamówienie '.$order->number"
    :preheader="$order->name.' · '.$order->money($order->total_gross).' · '.$shippingLabel"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 28px; line-height: 1.15; color: #2F2620;">Nowe zamówienie {{ $order->number }}</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        Opłacone: {{ $order->money($order->total_gross) }}, {{ $order->payment_method->label() }}.
        Odpowiedź na tego maila trafi prosto do osoby, która zamówiła.
    </p>

    @if ($missing->isNotEmpty())
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
            <tr>
                <td style="background: #F7ECE9; border: 1px solid #E0C3BC; border-radius: 4px; padding: 14px 16px; font-size: 14.5px; line-height: 1.6; color: #7A3A2E;">
                    {{ $nothingLeft ? 'Nic z tego zamówienia nie zostało na półce:' : 'Brakuje na półce:' }}
                    @foreach ($missing as $item){{ $item->product_name }} ({{ $item->variant_label }}, {{ $item->missing_quantity }} szt.){{ $loop->last ? '.' : ', ' }}@endforeach
                    Ktoś zapłacił za to chwilę wcześniej. Klientka dostała maila, że zwrócisz jej {{ $order->money($refund) }} najpóźniej w ciągu 14 dni. Podobną sztukę zrób, jeśli o nią poprosi.
                </td>
            </tr>
        </table>
    @endif

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">{{ $parcel ? 'Do spakowania' : 'Zamówienie' }}</div>
    <x-checkout::mail.items :order="$order" :items="$items" :subtotal="$subtotal" :shipping-label="$shippingLabel" />

    <div style="margin: 26px 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Dostawa</div>
    <p style="margin: 0; color: #2F2620;">{{ $shippingLabel }}</p>
    <p style="margin: 0; color: #5C5043;">{{ $delivery }}</p>
    <p style="margin: 0; color: #5C5043;">{{ $order->name }} · {{ $order->phone }} · {{ $order->email }}</p>
    @if ($order->invoice_nip)
        <p style="margin: 8px 0 0; color: #5C5043;">Faktura na NIP {{ $order->invoice_nip }}</p>
    @endif
    @if ($order->note)
        <p style="margin: 8px 0 0; color: #5C5043;">Dopisek: {{ $order->note }}</p>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 26px 0 0;">
        <tr>
            <td style="background: #2F2620; border-radius: 999px;">
                <a href="{{ route('admin.orders.show', $order) }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">Otwórz zamówienie w panelu</a>
            </td>
        </tr>
    </table>

    <x-slot:footer>
        Powiadomienie ze sklepu MellowAura. Wysyłam je na adres kontaktowy z ustawień panelu.
    </x-slot:footer>
</x-shared::mail.layout>
