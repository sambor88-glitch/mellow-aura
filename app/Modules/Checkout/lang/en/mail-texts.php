<?php

// The sentences of the order mails Kasia rewrites in the panel („Maile”); these go out while her field is empty.
// {numer} and {kwota} fill in from the order.
return [
    'order_awaiting_payment' => [
        'subject' => 'Your order {numer}',
        'intro' => 'Order {numer} is waiting for your payment.',
        'outro' => 'A question about your order? Just reply to this e-mail — it comes straight to me.',
    ],
    'order_confirmed' => [
        'subject' => 'Order {numer} is paid',
        'intro' => 'Order {numer} is paid.',
        'outro' => 'A question about your order? Just reply to this e-mail — it comes straight to me.',
    ],
    'order_unavailable' => [
        'subject' => 'Order {numer} — I’ll refund you in full',
        'intro' => 'I’ll refund your full payment — {kwota} — within 14 days at the latest.',
        'outro' => 'If you’d rather have a similar piece made to order, just reply to this e-mail.',
    ],
    'parcel_on_its_way' => [
        'subject' => 'Order {numer} is on its way',
        'intro' => 'I’ve sent your order {numer}.',
        'outro' => 'Something wrong with the parcel? Just reply to this e-mail — it comes straight to me.',
    ],
    'review_request' => [
        'subject' => 'How are you getting on with order {numer}?',
        'intro' => 'Your order {numer} is with you now. I hope everything arrived in one piece and you’re enjoying it.',
        'outro' => 'Something not right with your order? Just reply to this e-mail — it comes straight to me.',
    ],
];
