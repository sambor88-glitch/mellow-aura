<?php

// The sentences of the voucher mail Kasia rewrites in the panel („Maile”); these go out while her field is empty.
// {kod} fills in with the voucher code. A *_many default is worded for several vouchers in one mail.
return [
    'vouchers' => [
        'subject' => 'Voucher {kod}',
        'subject_many' => 'Vouchery z MellowAury',
        'intro' => 'Voucher jest w załączniku jako PDF — wydrukuj go albo prześlij dalej osobie, dla której jest. W tym mailu nie ma cen, więc możesz go przekazać w całości.',
        'intro_many' => 'Każdy voucher jest w załączniku jako osobny PDF — wydrukuj go albo prześlij dalej osobie, dla której jest. W tym mailu nie ma cen, więc możesz go przekazać w całości.',
        'outro' => 'Pytanie? Odpisz na tego maila — trafi prosto do mnie.',
    ],

    // „Podaruj wybór” (MA-235). {kod} fills in with the card's code, {data} with the last day to choose.
    'gift_choices' => [
        'subject' => 'Karta wyboru {kod}',
        'subject_many' => 'Karty wyboru z MellowAury',
        'intro' => 'Karta jest w załączniku jako PDF — wydrukuj ją albo prześlij dalej. W tym mailu nie ma cen, więc możesz go przekazać w całości.',
        'intro_many' => 'Każda karta jest w załączniku jako osobny PDF — wydrukuj ją albo prześlij dalej. W tym mailu nie ma cen, więc możesz go przekazać w całości.',
        'outro' => 'Pytanie? Odpisz na tego maila — trafi prosto do mnie.',
    ],
    'gift_choice_arrived' => [
        'subject' => 'Masz w prezencie kubek z napisem — Ty wybierasz',
        'intro' => 'Ktoś bliski podarował Ci kubek z MellowAury. Napis i kolor wnętrza wybierasz Ty — ja wbiję litery w glinę, wypalę kubek i wyślę go do Ciebie. Dostawa jest już opłacona.',
        'outro' => 'Pytanie? Odpisz na tego maila — trafi prosto do mnie.',
    ],
    'gift_choice_reminder' => [
        'subject' => 'Na wybór kubka zostało niewiele czasu',
        'intro' => 'Kubek z napisem czeka na Twój wybór do {data}. Wpisz tekst, wybierz kolor wnętrza i podaj adres — zajmie to kilka minut.',
        'intro_buyer' => 'Karta wyboru {kod} czeka na wybór do {data}. Przypomnij osobie, która ją dostała — napis, kolor i adres poda na stronie z karty.',
        'outro' => 'Pytanie? Odpisz na tego maila — trafi prosto do mnie.',
    ],
];
