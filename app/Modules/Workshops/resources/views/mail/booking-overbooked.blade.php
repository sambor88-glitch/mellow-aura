@php
    // Only the money that came in goes back to the account; an amount voucher's part goes back to the voucher (MA-244).
    $refund = collect([
        $order->amountDue() > 0 ? 'Zwrócę Ci '.($order->vouchers_gross > 0 ? '' : 'całą ').'wpłatę, '.$order->money($order->amountDue()).', najpóźniej w ciągu 14 dni.' : null,
        $order->vouchers_gross > 0 ? 'Kwotę z vouchera, '.$order->money($order->vouchers_gross).', oddam na voucher.' : null,
    ])->filter()->join(' ');
@endphp
<x-shared::mail.layout
    :title="'Termin się zapełnił'"
    :preheader="$refund"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Przepraszam — termin się zapełnił.</h1>
    <p style="margin: 0 0 16px; color: #5C5043;">
        {{ $mailTexts->html('booking_overbooked', 'intro', $textFill) }}
    </p>
    <p style="margin: 0 0 22px; color: #5C5043;">
        {{ $refund }}
        Jeśli wolisz inny termin, odpisz na tego maila — przepiszę Cię zamiast zwrotu.
    </p>
    @if ($contactPhone)
        <p style="margin: 0 0 4px; color: #5C5043;">Możesz też napisać na WhatsAppie: {{ $contactPhone }}.</p>
    @endif
    <x-shared::mail.signature />

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        Wiadomość o zapisie na warsztat {{ $order->number }}.
    </x-slot:footer>
</x-shared::mail.layout>
