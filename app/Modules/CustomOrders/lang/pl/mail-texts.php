<?php

// The sentences of the custom order mails Kasia rewrites in the panel („Maile”); these go out while her field is empty.
// {imie} and {kwota} fill in from the request and the payment link.
return [
    'brief_confirmed' => [
        'subject' => 'Mam Twój pomysł — odpiszę ze szkicem i wyceną',
        'intro' => 'Dziękuję, {imie}. Przeczytam wszystko i odpiszę ze szkicem i wyceną. Jeśli czegoś mi zabraknie, dopytam.',
        'outro' => 'Chcesz coś dopisać albo dosłać zdjęcie? Odpisz na tego maila — trafi prosto do mnie.',
    ],
    'payment_link' => [
        'subject' => 'Link do płatności: {kwota}',
        'intro' => '{imie}, zaczynam pracę, gdy tylko przyjdzie wpłata. Zapłacisz BLIK-iem, kartą albo przelewem przez Przelewy24.',
        'outro' => 'Masz pytanie przed zapłatą? Odpisz na tego maila — trafi prosto do mnie.',
    ],
];
