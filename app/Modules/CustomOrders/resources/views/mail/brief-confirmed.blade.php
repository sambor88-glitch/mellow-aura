<x-shared::mail.layout title="Twój pomysł na ceramikę" preheader="Mam Twój opis. Odpiszę ze szkicem i wyceną.">
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 28px; line-height: 1.15; color: #2F2620;">Mam Twój pomysł</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">{{ $mailTexts->html('brief_confirmed', 'intro', ['imie' => $customOrderRequest->name]) }}</p>

    @include('custom-orders::mail.partials.summary')

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Twój opis</div>
    <p style="margin: 0 0 22px; white-space: pre-line; color: #2F2620;">{{ $customOrderRequest->description }}</p>

    @if ($contactEmail)
        <p style="margin: 0; color: #5C5043;">{{ $mailTexts->html('brief_confirmed', 'outro', ['imie' => $customOrderRequest->name]) }}</p>
    @endif
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">{{ $mailTexts->common('signature') }}</p>

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        Potwierdzenie wysłane przez formularz „Zamówienia indywidualne” na stronie MellowAura.
    </x-slot:footer>
</x-shared::mail.layout>
