<?php

namespace App\Modules\Admin;

use App\Modules\Admin\Support\PanelAccess;
use Illuminate\Support\Facades\Route;

/**
 * Panel sections. Each screen belongs to its module; a section shows up once that screen has a route,
 * the same way links appear on the site, and only to an account that may open it.
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
            ['label' => 'Zamówienia indywidualne', 'route' => 'admin.custom-orders.index', 'active' => 'admin.custom-orders.*', 'description' => 'Pomysły z formularza ze zdjęciami: wycena, zaliczka, realizacja i wysyłka.'],
            ['label' => 'Odstąpienia', 'route' => 'admin.withdrawals.index', 'active' => 'admin.withdrawals.*', 'description' => 'Oświadczenia z formularza „Odstąp od umowy tutaj” i termin zwrotu pieniędzy.'],
            ['label' => 'Reklamacje', 'route' => 'admin.complaints.index', 'active' => 'admin.complaints.*', 'description' => 'Odpowiedź w 14 dni z gotowym listem — przy odmowie z oświadczeniem o mediacji.'],
            ['label' => 'Klientki', 'route' => 'admin.customers.index', 'active' => 'admin.customers.*', 'description' => 'Wszystko o jednej osobie po adresie e-mail: zamówienia, warsztaty, pomysły i wypały. Tu też kopia jej danych i usunięcie na jej prośbę (RODO).'],
            ['label' => 'Niewysłane maile', 'route' => 'admin.failed-mails.index', 'active' => 'admin.failed-mails.*', 'description' => 'Maile ze sklepu, które nie wyszły mimo kilku prób. Wyślesz je jeszcze raz jednym kliknięciem.'],
            ['label' => 'Produkty', 'route' => 'admin.products.index', 'active' => 'admin.products.*', 'description' => 'Nazwy, rozmiary, ceny, stany i zdjęcia.'],
            ['label' => 'Kategorie', 'route' => 'admin.categories.index', 'active' => 'admin.categories.*', 'description' => 'Tytuł i opis każdej półki w Google, wstęp nad produktami i podpisy skrótów nad sklepem.'],
            ['label' => 'Kubek z napisem', 'route' => 'admin.mug.edit', 'active' => 'admin.mug.*', 'description' => 'Zdjęcie pod napisy, rozmiary z cenami i limit znaków.'],
            ['label' => 'Prezenty i zestawy', 'route' => 'admin.gifts.edit', 'active' => 'admin.gifts.*', 'description' => 'Zestawy z rabatem, „Szukam prezentu” i vouchery.'],
            ['label' => 'Warsztaty', 'route' => 'admin.workshops.edit', 'active' => 'admin.workshops.*', 'description' => 'Cennik warsztatów, terminarz i lista zapisanych osób z kontaktem.'],
            ['label' => 'Zgłoszenia do wypału', 'route' => 'admin.firing.requests.index', 'active' => 'admin.firing.requests.*', 'description' => 'Wsady z formularza w tygodniowych partiach: nowe, przyjęte, w piecu, do odbioru.'],
            ['label' => 'Wypały', 'route' => 'admin.firing.edit', 'active' => 'admin.firing.edit', 'description' => 'Cennik wypałów na zlecenie, dzień wsadu i notka pod cennikiem.'],
            ['label' => 'Usługi', 'route' => 'admin.services.edit', 'active' => 'admin.services.*', 'description' => 'Apaszka i odcisk rośliny: zdjęcia przed i po, teksty, cennik i kroki.'],
            ['label' => 'Certyfikat unikatu', 'route' => 'admin.certificate.edit', 'active' => 'admin.certificate.*', 'description' => 'Nagłówek, cytat i dopisek pod logo na karcie do paczki — z podglądem PDF.'],
            ['label' => 'Maile', 'route' => 'admin.mail-texts.edit', 'active' => 'admin.mail-texts.*', 'description' => 'Temat, pierwsze i ostatnie zdanie maili do klientek, podpis i stopka — z podglądem.'],
            ['label' => 'Treści', 'route' => 'admin.content.edit', 'active' => 'admin.content.*', 'description' => 'Teksty na stronach, częste pytania, fakty o pracowni, zamówienia indywidualne, karty dla lokali i sprawy w formularzu.'],
            ['label' => 'Zdjęcia stron', 'route' => 'admin.page-photos.edit', 'active' => 'admin.page-photos.*', 'description' => 'Zdjęcia na stronie głównej, „O mnie”, kontakcie, usługach i pozostałych stronach: podmiana i kadr każdego miejsca.'],
            ['label' => 'Google', 'route' => 'admin.seo-pages.edit', 'active' => 'admin.seo-pages.*', 'description' => 'Tytuł i opis każdej strony stałej w wynikach wyszukiwania.'],
            ['label' => 'Ustawienia', 'route' => 'admin.settings.edit', 'active' => 'admin.settings.*', 'description' => 'E-mail, próg darmowej wysyłki i kod Paczkomatu.'],
            ['label' => 'Konta', 'route' => 'admin.accounts.index', 'active' => 'admin.accounts.*', 'description' => 'Kto ma dostęp do panelu: Ty i osoba, która pomaga przy zamówieniach.'],
        ], fn (array $section) => Route::has($section['route']) && PanelAccess::allows(auth()->user(), $section['route'])));
    }
}
