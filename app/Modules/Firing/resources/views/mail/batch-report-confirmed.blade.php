<x-shared::mail.layout title="Twój wsad do wypału" :preheader="'Wycena: '.$firingRequest->summary()['Wycena'].'. Odpiszę z potwierdzeniem terminu.'">
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 28px; line-height: 1.15; color: #2F2620;">Mam Twoje zgłoszenie</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        Dziękuję, {{ $firingRequest->name }}. Odpiszę z potwierdzeniem terminu i miejscem, gdzie przywieziesz prace.
        @if ($firingRequest->quote !== null)
            Wycena jest policzona z cennika i z Twojego zgłoszenia.
        @else
            Kwotę podam, gdy zobaczę prace albo ich zdjęcie.
        @endif
    </p>

    @include('firing::mail.partials.summary')

    @if ($firingRequest->message)
        <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Twoja wiadomość</div>
        <p style="margin: 0 0 22px; white-space: pre-line; color: #2F2620;">{{ $firingRequest->message }}</p>
    @endif
    @if ($note)
        <p style="margin: 0 0 22px; color: #5C5043;">{{ $note }}</p>
    @endif

    @if ($contactEmail)
        <p style="margin: 0; color: #5C5043;">Chcesz coś zmienić? Odpisz na tego maila — trafi prosto do mnie.</p>
    @endif
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">Kasia</p>

    <x-slot:footer>
        MellowAura{{ $city ? ' · '.$city : '' }}<br>
        Potwierdzenie zgłoszenia wysłane przez formularz „Zgłoś wsad” na stronie wypałów MellowAura.
    </x-slot:footer>
</x-shared::mail.layout>
