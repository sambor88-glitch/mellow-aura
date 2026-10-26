<x-shared::mail.layout
    :title="$invitation ? 'Dostęp do panelu MellowAury' : 'Nowe hasło do panelu MellowAury'"
    :preheader="$invitation ? 'Ustaw hasło i wejdź do panelu.' : 'Link działa '.$minutes.' minut.'"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 28px; line-height: 1.15; color: #2F2620;">{{ $invitation ? 'Dostęp do panelu' : 'Nowe hasło do panelu' }}</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        @if ($invitation)
            Masz konto w panelu sklepu MellowAura: {{ $user->email }}. Zanim się zalogujesz, ustaw hasło.
        @else
            Ktoś — pewnie Ty — poprosił o nowe hasło do panelu MellowAury dla {{ $user->email }}.
        @endif
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 22px;">
        <tr>
            <td style="background: #2F2620; border-radius: 999px;">
                <a href="{{ $url }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">{{ $invitation ? 'Ustaw hasło' : 'Ustaw nowe hasło' }}</a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 13.5px; color: #726456;">
        Link działa {{ $minutes }} minut.
        @if ($invitation)
            Gdy wygaśnie, na stronie logowania kliknij „Nie pamiętasz hasła?”.
        @else
            Jeśli to nie Ty, zignoruj tego maila — hasło się nie zmieni.
        @endif
    </p>

    <x-slot:footer>
        Wiadomość z panelu sklepu MellowAura. Nikt z pracowni nie poprosi Cię o hasło.
    </x-slot:footer>
</x-shared::mail.layout>
