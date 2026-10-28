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
];
