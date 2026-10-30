<x-shared::mail.layout
    :title="$choices->count() === 1 ? 'Karta wyboru '.$choices->first()->code : 'Karty wyboru'"
    preheader="Karta w PDF jest w załączniku — wydrukuj ją albo wyślij dalej."
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">{{ $choices->count() === 1 ? 'Twoja karta wyboru' : 'Twoje karty wyboru' }}</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        {{ $mailTexts->html('gift_choices', 'intro', $fill, $choices->count() > 1 ? 'many' : null) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; margin: 0 0 22px;">
        @foreach ($choices as $choice)
            <tr>
                <td style="padding: 12px 0; border-top: 1px solid #E2D7C7; vertical-align: top;">
                    <div style="font-size: 15px; color: #2F2620;">Kubek z napisem · {{ $choice->size_name }}</div>
                    <div style="font-size: 13px; color: #726456;">
                        {{ collect([$choice->recipient_name ? 'dla: '.$choice->recipient_name : null, $choice->sender_name ? 'od: '.$choice->sender_name : null, 'wybór do '.$choice->choose_by?->translatedFormat('j F Y')])->filter()->join(' · ') }}
                    </div>
                    @if ($choice->goesByEmail())
                        <div style="font-size: 13px; color: #726456;">Wyślę ją na {{ $choice->recipient_email }} {{ $choice->send_on->isToday() ? 'jeszcze dziś' : $choice->send_on->translatedFormat('j F') }} rano.</div>
                    @endif
                </td>
                <td align="right" style="padding: 12px 0 12px 16px; border-top: 1px solid #E2D7C7; vertical-align: top; white-space: nowrap; font-family: Georgia, 'Times New Roman', serif; font-size: 17px; letter-spacing: 1px; color: #2F2620;">{{ $choice->code }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin: 0 0 4px; color: #5C5043;">
        Osoba, która dostanie kartę, wpisze kod na stronie <a href="{{ route('gift-choice.index') }}" style="color: #855F3D;">{{ route('gift-choice.index') }}</a>, wymyśli napis, wybierze kolor wnętrza i poda adres. Dostawa jest już opłacona.
        @if ($contactEmail)
            {{ $mailTexts->html('gift_choices', 'outro', $fill) }}
        @endif
    </p>
    <p style="margin: 18px 0 0; color: #2F2620;">{{ $mailTexts->common('signature') }}</p>

    <x-slot:footer>{{ $mailTexts->common('footer') }}<br>{{ $choices->count() === 1 ? 'Karta' : 'Karty' }} do zamówienia {{ $order->number }} w sklepie MellowAura.</x-slot:footer>
</x-shared::mail.layout>
