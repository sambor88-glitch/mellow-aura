<x-shared::mail.layout
    :title="'Jutro warsztat'"
    :preheader="$date->dayLabel().', '.$date->timeLabel().($address ? ' · '.$address : '')"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Do jutra!</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        {{ $mailTexts->html('booking_reminder', 'intro', $textFill) }}
    </p>

    @include('workshops::mail.partials.booking')

    <p style="margin: 0 0 4px; color: #5C5043;">
        @if ($contactEmail)
            {{ $mailTexts->html('booking_reminder', 'outro', $textFill) }}
        @else
            Coś Ci wypadło albo się spóźnisz? Napisz do mnie przez stronę i podaj numer {{ $order->number }}.
        @endif
        @if ($contactPhone)
            Najszybciej złapiesz mnie na WhatsAppie: {{ $contactPhone }}.
        @endif
    </p>
    <x-shared::mail.signature />

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        Przypomnienie o warsztacie z zapisu {{ $order->number }}.
    </x-slot:footer>
</x-shared::mail.layout>
