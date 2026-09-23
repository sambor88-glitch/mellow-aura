<?php

/*
 * Two languages on one domain: Polish without a prefix, English under /en/.
 * The plan behind every decision here: „Plan wdrozenia - dwujezycznosc i sprzedaz UE.dc.html”.
 */

return [

    'default' => 'pl',

    /*
     * Languages that get their routes. English stays off until its texts are ready: a Polish text under an
     * English address is worse than no English page at all. Staging turns it on with APP_LOCALES=pl,en.
     */
    'enabled' => array_values(array_filter(array_map('trim', explode(',', (string) env('APP_LOCALES', 'pl'))))),

    'locales' => [
        'pl' => ['prefix' => '', 'currency' => 'PLN', 'og' => 'pl_PL', 'label' => 'Polski', 'short' => 'PL'],
        'en' => ['prefix' => 'en', 'currency' => 'EUR', 'og' => 'en_GB', 'label' => 'English', 'short' => 'EN'],
    ],

    /*
     * English addresses, keyed by route name. Every page listed here gets an English twin under /en/ with the
     * same controller and the name „en.<name>”; a page missing here exists in Polish only. A name whose route
     * does not exist yet is skipped until it does.
     *
     * Polish only by decision (23.09.2026): workshops and their vouchers, firing, the café offer, the scarf and
     * plant-imprint services, the journal and the gift finder.
     *
     * Not yet: the mug configurator and the gift sets come back with their euro prices and English texts (MA-127).
     */
    'paths' => [
        'en' => [
            'home' => '/',
            'shop.index' => 'shop',
            'shop.category' => 'shop/{category:slug}',
            'product.show' => 'product/{product:slug}',
            'cart.store' => 'basket',
            'cart.update' => 'basket/{line}',
            'cart.destroy' => 'basket/{line}',
            'checkout.index' => 'checkout',
            'checkout.store' => 'checkout',
            'checkout.confirmation' => 'checkout/confirmation',
            'content.about' => 'about',
            'content.studio' => 'studio',
            'content.contact' => 'contact',
            'content.contact.send' => 'contact',
            'content.faq' => 'shipping-and-care',
            'custom-orders.index' => 'custom-orders',
            'content.terms' => 'terms',
            'content.privacy' => 'privacy-policy',
            'withdrawal.create' => 'withdrawal',
            'withdrawal.store' => 'withdrawal',
            'withdrawal.confirmation' => 'withdrawal/confirmation',
            'consent.edit' => 'cookie-settings',
            'consent.store' => 'cookie-settings',
        ],
    ],

    /*
     * Where the language switch leads from a page that has no twin. Never the home page: Google treats that
     * as a soft 404 and the visitor loses the thread. The target page says in one sentence why.
     */
    'fallbacks' => [
        // A piece or a category not written in English yet.
        'product.show' => 'shop.index',
        'shop.category' => 'shop.index',
        'workshops.index' => 'content.about',
        'vouchers.index' => 'shop.index',
        'gifts.index' => 'shop.index',
        // Back on /en/ with euro prices (MA-127).
        'mug.index' => 'shop.index',
        'bundles.index' => 'shop.index',
        'journal.index' => 'content.about',
        'journal.show' => 'content.about',
        'firing.index' => 'content.studio',
        'content.b2b' => 'custom-orders.index',
        'content.imprint' => 'custom-orders.index',
        'content.scarf' => 'custom-orders.index',
    ],

];
