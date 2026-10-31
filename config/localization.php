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
     * Not yet: the gift sets come back with their euro prices and English texts (MA-127).
     *
     * Back once their texts are in English (MA-301): the content pages wait for MA-187, the terms, the privacy policy
     * and the withdrawal form for MA-131. Their texts are written in Polish in the views, so under /en/ they showed
     * Polish under an English address. Links to them from an English page keep the Polish address.
     */
    'paths' => [
        'en' => [
            'home' => '/',
            'shop.index' => 'shop',
            'shop.category' => 'shop/{category:slug}',
            'product.show' => 'product/{product:slug}',
            'stock-notifications.store' => 'notify-me',
            'stock-notifications.destroy' => 'notify-me/{token}',
            'mug.index' => 'custom-text-mug',
            'cart.store' => 'basket',
            'cart.update' => 'basket/{line}',
            'cart.destroy' => 'basket/{line}',
            'checkout.index' => 'checkout',
            'checkout.store' => 'checkout',
            'checkout.confirmation' => 'checkout/confirmation',
            'checkout.resume' => 'checkout/resume',
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
        // The page of a bought piece speaks the language of its order and has no twin (MA-234).
        'piece.show' => 'shop.index',
        'workshops.index' => 'shop.index',
        'workshops.booking.create' => 'shop.index',
        'workshops.booking.calendar' => 'shop.index',
        'workshops.booking.confirmation' => 'shop.index',
        'vouchers.index' => 'shop.index',
        'vouchers.pdf' => 'shop.index',
        'gifts.index' => 'shop.index',
        // A card for choosing a mug is bought and redeemed in złoty.
        'gift-choice.index' => 'shop.index',
        'gift-choice.show' => 'shop.index',
        // BLIK exists in złoty only; an English cart has no BLIK to wait for.
        'checkout.blik' => 'shop.index',
        'checkout.blik.status' => 'shop.index',
        // Back on /en/ with euro prices (MA-127).
        'bundles.index' => 'shop.index',
        'journal.index' => 'shop.index',
        'journal.show' => 'shop.index',
        'firing.index' => 'shop.index',
        'content.b2b' => 'shop.index',
        'content.imprint' => 'shop.index',
        'content.scarf' => 'shop.index',
        // The quote and its payment are written in Polish, like the custom order they belong to.
        'custom-orders.payment.show' => 'shop.index',
        'custom-orders.payment.confirmation' => 'shop.index',
        // Polish only until their texts are in English (MA-187, MA-131); see „paths” above.
        'content.about' => 'shop.index',
        'content.studio' => 'shop.index',
        'content.contact' => 'shop.index',
        'content.faq' => 'shop.index',
        'custom-orders.index' => 'shop.index',
        'content.terms' => 'shop.index',
        'content.privacy' => 'shop.index',
        'withdrawal.create' => 'shop.index',
        'withdrawal.confirmation' => 'shop.index',
    ],

];
