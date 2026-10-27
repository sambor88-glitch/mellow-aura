<x-shared::mail.layout
    :title="'Nowy zapis na warsztat '.$order->number"
    :preheader="$order->name.' · '.$booking->seats.' os. · '.$date->dayLabel().', '.$date->timeLabel()"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 28px; line-height: 1.15; color: #2F2620;">Nowy zapis na warsztat</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        {{ $order->number }} · {{ $voucher ? 'voucher '.$voucher->code.', bez płatności' : 'opłacone: '.$order->money($order->total_gross).', '.$order->payment_method->label() }}.
        Odpowiedź na tego maila trafi prosto do osoby, która się zapisała.
    </p>

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Warsztat</div>
    <p style="margin: 0; color: #2F2620;">{{ $workshop }}</p>
    <p style="margin: 0; color: #5C5043;">{{ $date->dayLabel() }}, {{ $date->timeLabel() }}</p>
    <p style="margin: 0 0 22px; color: #5C5043;">Liczba osób: {{ $booking->seats }} · na ten termin zostało: {{ $date->seatsLeftLabel() }}</p>

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Kto</div>
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
                <a href="{{ route('admin.orders.show', $order) }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">Otwórz zapis w panelu</a>
            </td>
        </tr>
    </table>
</x-shared::mail.layout>
