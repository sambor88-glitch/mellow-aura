<?php

// The sentences of the order mails Kasia rewrites in the panel („Maile”); these go out while her field is empty.
// {numer} and {kwota} fill in from the order.
return [
    'order_awaiting_payment' => [
        'subject' => 'Podsumowanie zamówienia {numer}',
        'intro' => 'Zamówienie {numer} czeka na płatność.',
        'outro' => 'Masz pytanie o zamówienie? Odpisz na tego maila — trafi prosto do mnie.',
    ],
    'order_confirmed' => [
        'subject' => 'Zamówienie {numer} jest opłacone',
        'intro' => 'Zamówienie {numer} jest opłacone.',
        'outro' => 'Masz pytanie o zamówienie? Odpisz na tego maila — trafi prosto do mnie.',
    ],
    'order_unavailable' => [
        'subject' => 'Zamówienie {numer} — zwrócę całą wpłatę',
        'intro' => 'Zwrócę Ci całą wpłatę — {kwota} — najpóźniej w ciągu 14 dni.',
        'outro' => 'Jeśli wolisz podobną sztukę na zamówienie, odpisz na tego maila.',
    ],
    'parcel_on_its_way' => [
        'subject' => 'Zamówienie {numer} jest w drodze',
        'intro' => 'Wysłałam Twoje zamówienie {numer}.',
        'outro' => 'Coś nie tak z paczką? Odpisz na tego maila — trafi prosto do mnie.',
    ],
    'withdrawal_confirmed' => [
        'subject' => 'Potwierdzenie odstąpienia od umowy — {numer}',
        'outro' => 'Masz pytanie? Odpisz na tego maila — trafi prosto do mnie.',
    ],
];
