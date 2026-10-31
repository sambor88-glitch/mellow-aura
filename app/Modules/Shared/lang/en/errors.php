<?php

// The error pages (resources/views/errors), through App\Modules\Shared\Support\ErrorPage.
return [
    'tagline' => 'ceramics · handmade · kraków',
    'shop' => 'Go to the shop',
    'home' => 'Home page',

    '404' => [
        'title' => 'Page not found',
        'eyebrow' => 'page not found',
        'heading' => 'This page is not here',
        'lead' => 'The address may have changed, or a letter went missing from the link. Have a look at the shop or start from the home page.',
    ],
    '4xx' => [
        'title' => 'This page cannot be opened',
        'eyebrow' => 'cannot be opened',
        'heading' => 'This page cannot be opened',
        'lead' => 'Check the address or start from the home page. If a link on my site led you here, write to me and I will fix it.',
    ],
    '419' => [
        'title' => 'Page expired',
        'eyebrow' => 'page expired',
        'heading' => 'This page was open for too long',
        'lead' => 'For safety a form expires after a while. Go back, refresh the page and send it once more.',
        'back' => 'Back to the form',
    ],
    '429' => [
        'title' => 'Too many tries',
        'eyebrow' => 'a short pause',
        'heading' => 'Too many tries in a short time',
        'lead' => 'This is how the site protects itself from bots that send forms by the hundred. Wait a minute and try again.',
    ],
    '503' => [
        'title' => 'A short break',
        'eyebrow' => 'a short break',
        'heading' => 'I am tidying up the site',
        'lead' => 'In a few minutes everything will be back in place, your basket too. Come back in a moment.',
        'refresh' => 'Refresh the page',
    ],
    '5xx' => [
        'title' => 'Something went wrong',
        'eyebrow' => 'a temporary fault',
        'heading' => 'Something went wrong on my side',
        'lead' => 'Your basket is safe — try again in a moment. If it keeps happening, write to me.',
        'retry' => 'Try again',
    ],
];
