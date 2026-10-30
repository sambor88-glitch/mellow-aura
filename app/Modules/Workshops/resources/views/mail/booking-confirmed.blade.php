<x-shared::mail.layout
    :title="'Zapis na warsztat '.$order->number"
    :preheader="$workshop.' · '.$date->dayLabel().', '.$date->timeLabel()"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Dziękuję. Do zobaczenia w pracowni.</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        {{ $mailTexts->html('booking_confirmed', 'intro', $textFill, $booking->seats > 1 ? 'many' : null) }}
    </p>

    @include('workshops::mail.partials.booking')

    <p style="margin: 0 0 4px; color: #5C5043;">
        @if ($booking->remindsDayBefore())
            Dzień przed warsztatem przypomnę Ci o nim mailem.
        @endif
        @if ($contactEmail)
            {{ $mailTexts->html('booking_confirmed', 'outro', $textFill) }}
        @else
            Masz pytanie albo coś Ci wypadło? Napisz do mnie przez stronę i podaj numer {{ $order->number }}.
        @endif
        @if ($contactPhone)
            Możesz też napisać na WhatsAppie: {{ $contactPhone }}.
        @endif
    </p>
    <p style="margin: 12px 0 0; font-size: 13.5px; color: #5C5043;">
        W załącznikach są regulamin{{ $order->terms_version ? ' ('.$order->terms_version.')' : '' }} i warsztat do dodania w Twoim kalendarzu.
    </p>
    <x-shared::mail.signature />

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        Potwierdzenie zapisu na warsztat {{ $order->number }}.
    </x-slot:footer>
</x-shared::mail.layout>
