<?php

// The BLIK page after the code is taken (BlikController, MA-101). BLIK takes only złoty, so this is rarely seen.
return [
    'title' => 'BLIK payment | MellowAura',
    'eyebrow' => 'checkout &middot; BLIK',
    'wait_heading' => 'Confirm the payment in your banking app',
    'wait_text' => 'Order :number is waiting. Once your bank confirms, this page moves on by itself.',
    'checking' => 'Waiting for your bank to confirm',
    'time_left' => 'Time left to confirm: :time',
    'noscript' => 'This page checks the payment every 5 seconds.',
    'expired_heading' => 'The code has expired',
    'expired_text' => 'Your bank didn’t get a confirmation within 2 minutes. Generate a new code in your app and enter it here — order :number and your basket are waiting.',
    'refused_heading' => 'Your bank didn’t accept the code',
    'refused_text' => 'Generate a new code in your banking app and enter it here — order :number and your basket are waiting.',
    'label' => 'New code from your banking app',
    'hint' => '6 digits, valid for 2 minutes',
    'pay' => 'Pay :amount',
    'other' => 'I’d rather pay by card or transfer',
];
