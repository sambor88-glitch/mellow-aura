<x-shared::mail.layout
    :title="'Termin się zapełnił'"
    :preheader="'Zwrócę Ci całą wpłatę '.$order->money($order->total_gross).'.'"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Przepraszam — termin się zapełnił.</h1>
    <p style="margin: 0 0 16px; color: #5C5043;">
        Twoja płatność za warsztat {{ $date->dayLabel() }}, {{ $date->timeLabel() }} (zapis {{ $order->number }}) dotarła, kiedy ostatnie miejsce zajęła już inna osoba.
    </p>
    <p style="margin: 0 0 22px; color: #5C5043;">
        Zwrócę Ci całą wpłatę, {{ $order->money($order->total_gross) }}, najpóźniej w ciągu 14 dni.
        Jeśli wolisz inny termin, odpisz na tego maila — przepiszę Cię zamiast zwrotu.
    </p>
    @if ($contactPhone)
        <p style="margin: 0 0 4px; color: #5C5043;">Możesz też napisać na WhatsAppie: {{ $contactPhone }}.</p>
    @endif
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">{{ $mailTexts->common('signature') }}</p>

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        Wiadomość o zapisie na warsztat {{ $order->number }}.
    </x-slot:footer>
</x-shared::mail.layout>
