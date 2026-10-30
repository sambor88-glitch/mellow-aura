@php
    // An amount voucher's part is already back on its balance (CancelBooking); only the rest is money to return (MA-244).
    $refund = $order->amountDue() > 0 ? 'Zwrócę Ci '.($order->vouchers_gross > 0 ? '' : 'całą ').'wpłatę, '.$order->money($order->amountDue()).', najpóźniej w ciągu 14 dni, na konto, z którego przyszła.' : null;
    $backOnVoucher = $order->vouchers_gross > 0 ? 'Kwota z vouchera, '.$order->money($order->vouchers_gross).', wróciła już na voucher — wykorzystasz ją w sklepie albo przy kolejnym zapisie.' : null;
@endphp
<x-shared::mail.layout
    :title="'Zapis na warsztat anulowany'"
    :preheader="$voucher ? 'Twój voucher wraca do Ciebie.' : ($refund !== null ? 'Zwrócę Ci '.($backOnVoucher ? '' : 'całą ').'wpłatę '.$order->money($order->amountDue()).'.' : 'Kwota z vouchera wraca na voucher.')"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Twój zapis jest anulowany.</h1>
    <p style="margin: 0 0 16px; color: #5C5043;">
        {{ $mailTexts->html('booking_cancelled', 'intro', $textFill) }}
    </p>
    @if ($voucher && ! $voucher['expired'])
        <p style="margin: 0 0 22px; color: #5C5043;">
            Twój voucher {{ $voucher['code'] }} znów jest ważny — do {{ $voucher['valid_until'] }}. Zapiszesz się nim na inny termin na stronie.
            Jeśli wolisz, odpisz na tego maila — przepiszę Cię sama.
        </p>
    @elseif ($voucher)
        <p style="margin: 0 0 22px; color: #5C5043;">
            Twój voucher {{ $voucher['code'] }} wraca do Ciebie. Jego ważność minęła {{ $voucher['valid_until'] }} — odpisz na tego maila, a przedłużę go i zapiszę Cię na inny termin.
        </p>
    @else
        <p style="margin: 0 0 22px; color: #5C5043;">
            {{ collect([$refund, $backOnVoucher])->filter()->join(' ') }}
            Jeśli wolisz inny termin, odpisz na tego maila — przepiszę Cię zamiast zwrotu.
        </p>
    @endif
    @if ($contactPhone)
        <p style="margin: 0 0 4px; color: #5C5043;">Możesz też napisać na WhatsAppie: {{ $contactPhone }}.</p>
    @endif
    <p style="margin: 12px 0 0; font-size: 13.5px; color: #5C5043;">Plik w załączniku usunie warsztat z Twojego kalendarza.</p>
    <x-shared::mail.signature />

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        Wiadomość o zapisie na warsztat {{ $order->number }}.
    </x-slot:footer>
</x-shared::mail.layout>
