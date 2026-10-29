<x-shared::mail.layout
    :title="'Nowy termin warsztatu '.$order->number"
    :preheader="$workshop.' · '.$date->dayLabel().', '.$date->timeLabel()"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Masz nowy termin warsztatu.</h1>
    <p style="margin: 0 0 8px; color: #5C5043;">
        {{ $mailTexts->html('booking_moved', 'intro', $textFill) }}
    </p>
    <p style="margin: 0 0 22px; font-size: 13.5px; color: #5C5043;">
        Poprzedni termin: <span style="text-decoration: line-through;">{{ $previous }}</span>. Płatność zostaje ta sama — niczego nie dopłacasz.
    </p>

    @include('workshops::mail.partials.booking')

    <p style="margin: 0 0 4px; color: #5C5043;">
        @if ($booking->remindsDayBefore())
            Dzień przed warsztatem przypomnę Ci o nim mailem.
        @endif
        @if ($contactEmail)
            {{ $mailTexts->html('booking_moved', 'outro', $textFill) }}
        @else
            Masz pytanie albo ten termin Ci nie pasuje? Napisz do mnie przez stronę i podaj numer {{ $order->number }}.
        @endif
        @if ($contactPhone)
            Możesz też napisać na WhatsAppie: {{ $contactPhone }}.
        @endif
    </p>
    <p style="margin: 12px 0 0; font-size: 13.5px; color: #5C5043;">W załączniku jest nowy termin do kalendarza — jeśli masz tam warsztat, przesunie się sam.</p>
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">{{ $mailTexts->common('signature') }}</p>

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        Nowy termin warsztatu z zapisu {{ $order->number }}.
    </x-slot:footer>
</x-shared::mail.layout>
