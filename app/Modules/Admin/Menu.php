<?php

namespace App\Modules\Admin;

use Illuminate\Support\Facades\Route;

/**
 * Panel sections. Each screen belongs to its module; a section shows up once that screen has a route,
 * the same way links appear on the site.
 */
class Menu
{
    /**
     * @return list<array{label: string, route: string, active: string, description: string}>
     */
    public static function sections(): array
    {
        return array_values(array_filter([
            ['label' => 'Zamówienia', 'route' => 'admin.orders.index', 'active' => 'admin.orders.*', 'description' => 'Kto zapłacił, co wysłać i jaki napis wbić w glinę.'],
            ['label' => 'Odstąpienia', 'route' => 'admin.withdrawals.index', 'active' => 'admin.withdrawals.*', 'description' => 'Oświadczenia z formularza „Odstąp od umowy tutaj” i termin zwrotu pieniędzy.'],
            ['label' => 'Reklamacje', 'route' => 'admin.complaints.index', 'active' => 'admin.complaints.*', 'description' => 'Odpowiedź w 14 dni z gotowym listem — przy odmowie z oświadczeniem o mediacji.'],
            ['label' => 'Produkty', 'route' => 'admin.products.index', 'active' => 'admin.products.*', 'description' => 'Nazwy, rozmiary, ceny, stany i zdjęcia.'],
            ['label' => 'Kubek z napisem', 'route' => 'admin.mug.edit', 'active' => 'admin.mug.*', 'description' => 'Zdjęcie pod napisy, rozmiary z cenami i limit znaków.'],
            ['label' => 'Prezenty i zestawy', 'route' => 'admin.gifts.edit', 'active' => 'admin.gifts.*', 'description' => 'Zestawy z rabatem, „Szukam prezentu” i vouchery.'],
            ['label' => 'Warsztaty', 'route' => 'admin.workshops.edit', 'active' => 'admin.workshops.*', 'description' => 'Cennik warsztatów na stronie. Zapisy i terminy dojdą po świętach.'],
            ['label' => 'Wypały', 'route' => 'admin.firing.edit', 'active' => 'admin.firing.*', 'description' => 'Cennik wypałów na zlecenie i notka o wsadzie.'],
            ['label' => 'Usługi', 'route' => 'admin.services.edit', 'active' => 'admin.services.*', 'description' => 'Apaszka i odcisk rośliny: zdjęcia przed i po, teksty, cennik i kroki.'],
            ['label' => 'Treści', 'route' => 'admin.content.edit', 'active' => 'admin.content.*', 'description' => 'Teksty na stronach, częste pytania, fakty o pracowni, zamówienia indywidualne, karty dla lokali i sprawy w formularzu.'],
            ['label' => 'Ustawienia', 'route' => 'admin.settings.edit', 'active' => 'admin.settings.*', 'description' => 'E-mail, próg darmowej wysyłki i kod Paczkomatu.'],
        ], fn (array $section) => Route::has($section['route'])));
    }
}
