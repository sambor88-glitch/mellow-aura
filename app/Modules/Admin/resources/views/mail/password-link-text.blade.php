{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
{!! $invitation ? 'Dostęp do panelu' : 'Nowe hasło do panelu' !!}

{!! $invitation
    ? 'Masz konto w panelu sklepu MellowAura: '.$user->email.'. Zanim się zalogujesz, ustaw hasło.'
    : 'Ktoś — pewnie Ty — poprosił o nowe hasło do panelu MellowAury dla '.$user->email.'.' !!}

{!! $invitation ? 'Ustaw hasło' : 'Ustaw nowe hasło' !!}: {!! $url !!}

Link działa {{ $minutes }} minut. {!! $invitation ? 'Gdy wygaśnie, na stronie logowania kliknij „Nie pamiętasz hasła?”.' : 'Jeśli to nie Ty, zignoruj tego maila — hasło się nie zmieni.' !!}

--
Wiadomość z panelu sklepu MellowAura. Nikt z pracowni nie poprosi Cię o hasło.
