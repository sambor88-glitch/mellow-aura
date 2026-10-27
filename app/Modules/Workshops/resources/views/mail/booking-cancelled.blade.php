<x-shared::mail.layout
    :title="'Zapis na warsztat anulowany'"
    :preheader="'Zwrócę Ci całą wpłatę '.$order->money($order->total_gross).'.'"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Twój zapis jest anulowany.</h1>
    <p style="margin: 0 0 16px; color: #5C5043;">
        Anulowałam Twój zapis na warsztat {{ $date->dayLabel() }}, {{ $date->timeLabel() }} (zapis {{ $order->number }}).
    </p>
    <p style="margin: 0 0 22px; color: #5C5043;">
        Zwrócę Ci całą wpłatę, {{ $order->money($order->total_gross) }}, najpóźniej w ciągu 14 dni, na konto, z którego przyszła.
        Jeśli wolisz inny termin, odpisz na tego maila — przepiszę Cię zamiast zwrotu.
    </p>
    @if ($contactPhone)
        <p style="margin: 0 0 4px; color: #5C5043;">Możesz też napisać na WhatsAppie: {{ $contactPhone }}.</p>
    @endif
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">Kasia</p>

    <x-slot:footer>
        MellowAura{{ $city ? ' · '.$city : '' }}<br>
        Wiadomość o zapisie na warsztat {{ $order->number }}.
    </x-slot:footer>
</x-shared::mail.layout>
