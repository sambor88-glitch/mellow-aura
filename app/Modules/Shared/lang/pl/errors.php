<?php

// The error pages (resources/views/errors), through App\Modules\Shared\Support\ErrorPage.
return [
    'tagline' => 'ceramika · rękodzieło · kraków',
    'shop' => 'Przejdź do sklepu',
    'home' => 'Strona główna',

    '404' => [
        'title' => 'Nie ma tej strony',
        'eyebrow' => 'nie ma tej strony',
        'heading' => 'Tej strony tu nie ma',
        'lead' => 'Adres mógł się zmienić albo w linku zabrakło litery. Zajrzyj do sklepu albo zacznij od strony głównej.',
    ],
    '4xx' => [
        'title' => 'Nie da się otworzyć tej strony',
        'eyebrow' => 'nie da się otworzyć',
        'heading' => 'Tej strony nie da się otworzyć',
        'lead' => 'Sprawdź adres albo zacznij od strony głównej. Jeśli link na mojej stronie prowadzi tutaj, napisz do mnie — poprawię go.',
    ],
    '419' => [
        'title' => 'Strona wygasła',
        'eyebrow' => 'strona wygasła',
        'heading' => 'Ta strona była otwarta zbyt długo',
        'lead' => 'Dla bezpieczeństwa formularz wygasa po pewnym czasie. Wróć, odśwież stronę i wyślij go jeszcze raz.',
        'back' => 'Wróć do formularza',
    ],
    '429' => [
        'title' => 'Za dużo prób',
        'eyebrow' => 'chwila przerwy',
        'heading' => 'Za dużo prób w krótkim czasie',
        'lead' => 'Tak strona chroni się przed automatami, które wysyłają formularze setkami. Odczekaj minutę i spróbuj jeszcze raz.',
    ],
    '503' => [
        'title' => 'Krótka przerwa',
        'eyebrow' => 'krótka przerwa',
        'heading' => 'Robię porządki na stronie',
        'lead' => 'Za kilka minut wszystko wróci na swoje miejsce, razem z Twoim koszykiem. Zajrzyj za chwilę.',
        'refresh' => 'Odśwież stronę',
    ],
    '5xx' => [
        'title' => 'Coś się zacięło',
        'eyebrow' => 'chwilowa usterka',
        'heading' => 'Coś się zacięło po mojej stronie',
        'lead' => 'Twój koszyk jest bezpieczny — spróbuj za chwilę. Jeśli to się powtarza, napisz do mnie przez stronę kontaktową.',
        'retry' => 'Spróbuj jeszcze raz',
    ],
];
