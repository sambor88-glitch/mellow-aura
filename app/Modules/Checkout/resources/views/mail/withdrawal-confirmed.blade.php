<x-shared::mail.layout
    :title="'Odstąpienie od umowy — '.$withdrawal->order_number"
    :preheader="'Twoje oświadczenie doszło '.$withdrawal->submittedAtLabel().'. Poniżej jego treść i co dalej.'"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 28px; line-height: 1.15; color: #2F2620;">Dostałam Twoje oświadczenie</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        Potwierdzam, że oświadczenie o odstąpieniu od umowy doszło do mnie {{ $withdrawal->submittedAtLabel() }}.
        Zachowaj tego maila — potwierdza datę i godzinę wysłania oświadczenia.
    </p>

    <x-checkout::mail.withdrawal-statement :withdrawal="$withdrawal" />

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Co dalej</div>
    <p style="margin: 0 0 10px; color: #2F2620;">
        Rzeczy odeślij w ciągu 14 dni od dziś{{ $returnAddress ? ' na adres: '.$returnAddress : '' }}.
        Koszt odesłania jest po Twojej stronie. Ceramikę zapakuj tak, żeby nie stłukła się w drodze.
    </p>
    <p style="margin: 0 0 22px; color: #2F2620;">
        Pieniądze zwrócę w ciągu 14 dni od dziś, tą samą metodą płatności. Mogę z tym poczekać, aż rzeczy do mnie wrócą
        albo dostanę dowód ich nadania. Szczegóły są w regulaminie, w §12: <a href="{{ route('content.terms') }}#regulamin-12-prawo-odstapienia-od-umowy" style="color: #855F3D;">{{ route('content.terms') }}</a>.
    </p>

    @if ($contactEmail)
        <p style="margin: 0; color: #5C5043;">Masz pytanie? Odpisz na tego maila — trafi prosto do mnie.</p>
    @endif
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">Kasia</p>

    <x-slot:footer>
        MellowAura{{ $city ? ' · '.$city : '' }}<br>
        Potwierdzenie odstąpienia od umowy wysłane przez formularz „Odstąp od umowy tutaj” w sklepie MellowAura.
    </x-slot:footer>
</x-shared::mail.layout>
