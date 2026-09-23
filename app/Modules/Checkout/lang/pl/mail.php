<?php

// The parts of the order e-mails shared by every letter; the letters themselves are templates per language.
return [
    'from' => 'Kasia z MellowAury',
    'subject' => [
        'confirmed' => 'Zamówienie :number jest opłacone',
        'awaiting' => 'Podsumowanie zamówienia :number',
        'unavailable' => 'Zamówienie :number — zwrócę całą wpłatę',
        'parcel' => 'Zamówienie :number jest w drodze',
    ],
    'items' => [
        'text' => 'Napis',
        'glaze' => 'Kolor wnętrza',
        'deviation' => 'Zaakceptowana cecha',
        'subtotal' => 'Produkty',
        'delivery' => 'Dostawa',
        'free' => 'gratis',
        'total' => 'Razem',
    ],
    'delivery' => [
        'email' => 'Na adres :email',
        'locker' => 'Paczkomat :code',
        'locker_by_phone' => 'Paczkomat dobiorę po numerze telefonu :phone',
        'courier' => 'Adres dostawy potwierdzę z Tobą przed wysyłką',
        'pickup' => 'Napiszę, kiedy i gdzie możesz odebrać zamówienie',
    ],
];
