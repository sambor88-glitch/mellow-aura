<?php

// The sentences of the workshop mails Kasia rewrites in the panel („Maile”); these go out while her field is empty.
// {numer}, {termin}, {dzien} and {godzina} fill in from the booking. A *_many default is worded for more than one seat.
return [
    'booking_confirmed' => [
        'subject' => 'Do zobaczenia w pracowni · {dzien}, {godzina}',
        'intro' => 'Zapis {numer} jest opłacony — miejsce jest Twoje.',
        'intro_many' => 'Zapis {numer} jest opłacony — miejsca są Wasze.',
        'outro' => 'Masz pytanie albo coś Ci wypadło? Odpisz na tego maila — trafi prosto do mnie.',
    ],
    'booking_reminder' => [
        'subject' => 'Jutro warsztat · {godzina}',
        'intro' => 'Przypominam o warsztacie — glina już czeka. Wszystko jest w tym mailu, nie musisz szukać poprzedniego.',
        'outro' => 'Coś Ci wypadło albo się spóźnisz? Odpisz na tego maila — trafi prosto do mnie.',
    ],
    'booking_cancelled' => [
        'subject' => 'Zapis na warsztat anulowany · {dzien}, {godzina}',
        'intro' => 'Anulowałam Twój zapis na warsztat {termin} (zapis {numer}).',
    ],
    'booking_overbooked' => [
        'subject' => 'Termin warsztatu się zapełnił · {numer}',
        'intro' => 'Twoja płatność za warsztat {termin} (zapis {numer}) dotarła, kiedy ostatnie miejsce zajęła już inna osoba.',
    ],
];
