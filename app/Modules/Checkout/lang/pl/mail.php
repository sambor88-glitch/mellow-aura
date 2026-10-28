<?php

// The parts of the order e-mails shared by every letter; the letters themselves are templates per language.
return [
    'from' => 'Kasia z MellowAury',
    'items' => [
        'text' => 'Napis',
        'glaze' => 'Kolor wnętrza',
        'deviation' => 'Zaakceptowana cecha',
        'subtotal' => 'Produkty',
        'delivery' => 'Dostawa',
        'free' => 'gratis',
        'voucher' => 'Voucher',
        'due' => 'Dopłata',
        'total' => 'Razem',
    ],
    'delivery' => [
        'email' => 'Na adres :email',
        'locker' => 'Paczkomat :code',
        'locker_to_agree' => 'Napiszę do Ciebie i ustalimy paczkomat',
        'courier' => 'Adres dostawy potwierdzę z Tobą przed wysyłką',
        'pickup' => 'Napiszę, kiedy i gdzie możesz odebrać zamówienie',
    ],
    // The made-up order the panel previews the mails with.
    'sample' => [
        'product' => 'Miska ze szkliwem',
        'variant' => 'Średnia',
    ],
];
