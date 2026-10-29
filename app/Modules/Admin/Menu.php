<?php

namespace App\Modules\Admin;

use App\Modules\Admin\Support\PanelAccess;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\ComplaintAnswer;
use App\Modules\Checkout\Models\Order;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Monitoring\Support\FailedMails;
use Closure;
use Illuminate\Support\Facades\Route;

/**
 * Panel sections in four groups (MA-249). Each screen belongs to its module; a section shows up once that screen has a route,
 * the same way links appear on the site, and only to an account that may open it. A group left empty is gone, so a helper
 * sees only the orders.
 *
 * Four sections count what waits for Kasia. Only the sections this account sees are counted, once per request: the layout
 * and the dashboard both ask.
 */
class Menu
{
    /**
     * @return list<array{label: string, sections: list<array{label: string, route: string, active: list<string>, description: string, count: int, count_label: string}>}>
     */
    public static function groups(): array
    {
        $request = request();

        if ($request->attributes->has(self::class)) {
            return $request->attributes->get(self::class);
        }

        $groups = collect(self::definitions())
            ->map(fn (array $group) => [
                'label' => $group['label'],
                'sections' => collect($group['sections'])
                    ->filter(fn (array $section) => Route::has($section['route']) && PanelAccess::allows(auth()->user(), $section['route']))
                    ->map(fn (array $section) => [
                        ...$section,
                        'count' => isset($section['count']) ? (int) $section['count']() : 0,
                        'count_label' => $section['count_label'] ?? '',
                    ])
                    ->values()
                    ->all(),
            ])
            ->reject(fn (array $group) => $group['sections'] === [])
            ->values()
            ->all();

        $request->attributes->set(self::class, $groups);

        return $groups;
    }

    /**
     * Every section in one list, in the order of the menu.
     *
     * @return list<array{label: string, route: string, active: list<string>, description: string, count: int, count_label: string}>
     */
    public static function sections(): array
    {
        return array_merge([], ...array_column(self::groups(), 'sections'));
    }

    /** What waits in all the sections together: the number on the menu button on a phone. */
    public static function waiting(): int
    {
        return array_sum(array_column(self::sections(), 'count'));
    }

    /**
     * Whether the page open now is one of the section's screens.
     *
     * @param  array{active: list<string>}  $section
     */
    public static function isCurrent(array $section): bool
    {
        return request()->routeIs(...$section['active']);
    }

    /**
     * @return list<array{label: string, sections: list<array{label: string, route: string, active: list<string>, description: string, count?: Closure(): int, count_label?: string}>}>
     */
    private static function definitions(): array
    {
        return [
            ['label' => 'Sprzedaż', 'sections' => [
                ['label' => 'Zamówienia', 'route' => 'admin.orders.index', 'active' => ['admin.orders.*'], 'description' => 'Kto zapłacił, co wysłać i jaki napis wbić w glinę.',
                    'count' => fn () => Order::query()->where('payment_status', PaymentStatus::Paid)->where('status', OrderStatus::InProgress)->count(),
                    'count_label' => 'do wysłania'],
                ['label' => 'Zamówienia indywidualne', 'route' => 'admin.custom-orders.index', 'active' => ['admin.custom-orders.*'], 'description' => 'Pomysły z formularza ze zdjęciami: wycena, zaliczka, realizacja i wysyłka.',
                    'count' => fn () => CustomOrderRequest::query()->where('status', CustomOrderStatus::New)->count(),
                    'count_label' => 'nowe'],
                ['label' => 'Klientki', 'route' => 'admin.customers.index', 'active' => ['admin.customers.*'], 'description' => 'Wszystko o jednej osobie po adresie e-mail: zamówienia, warsztaty, pomysły i wypały. Tu też kopia jej danych i usunięcie na jej prośbę (RODO).'],
                ['label' => 'Odstąpienia', 'route' => 'admin.withdrawals.index', 'active' => ['admin.withdrawals.*'], 'description' => 'Oświadczenia z formularza „Odstąp od umowy tutaj” i termin zwrotu pieniędzy.'],
                // Complaints arrive by e-mail; the panel keeps only the answers, so what waits is an answer that never went out.
                ['label' => 'Reklamacje', 'route' => 'admin.complaints.index', 'active' => ['admin.complaints.*'], 'description' => 'Odpowiedź w 14 dni z gotowym listem — przy odmowie z oświadczeniem o mediacji.',
                    'count' => fn () => ComplaintAnswer::query()->whereNull('emailed_at')->count(),
                    'count_label' => 'odpowiedzi nie wyszły'],
            ]],
            ['label' => 'Oferta', 'sections' => [
                ['label' => 'Produkty', 'route' => 'admin.products.index', 'active' => ['admin.products.*'], 'description' => 'Nazwy, rozmiary, ceny, stany i zdjęcia.'],
                ['label' => 'Kategorie', 'route' => 'admin.categories.index', 'active' => ['admin.categories.*'], 'description' => 'Tytuł i opis każdej półki w Google, wstęp nad produktami i podpisy skrótów nad sklepem.'],
                ['label' => 'Kubek z napisem', 'route' => 'admin.mug.edit', 'active' => ['admin.mug.*'], 'description' => 'Zdjęcie pod napisy, rozmiary z cenami i limit znaków.'],
                ['label' => 'Prezenty i zestawy', 'route' => 'admin.gifts.edit', 'active' => ['admin.gifts.*'], 'description' => 'Zestawy z rabatem, „Szukam prezentu” i vouchery.'],
                ['label' => 'Warsztaty', 'route' => 'admin.workshops.edit', 'active' => ['admin.workshops.*'], 'description' => 'Cennik warsztatów, terminarz i lista zapisanych osób z kontaktem.'],
                // One section for both screens: the batches from the form and the price list, as two tabs on the screen.
                ['label' => 'Wypały', 'route' => 'admin.firing.requests.index', 'active' => ['admin.firing.*'], 'description' => 'Wsady z formularza w tygodniowych partiach, cennik wypałów na zlecenie i dzień wsadu.'],
                ['label' => 'Usługi', 'route' => 'admin.services.edit', 'active' => ['admin.services.*'], 'description' => 'Apaszka i odcisk rośliny: zdjęcia przed i po, teksty, cennik i kroki.'],
            ]],
            ['label' => 'Strona', 'sections' => [
                ['label' => 'Treści', 'route' => 'admin.content.edit', 'active' => ['admin.content.*'], 'description' => 'Teksty na stronach, częste pytania, fakty o pracowni, zamówienia indywidualne, karty dla lokali i sprawy w formularzu.'],
                ['label' => 'Zdjęcia stron', 'route' => 'admin.page-photos.edit', 'active' => ['admin.page-photos.*'], 'description' => 'Zdjęcia na stronie głównej, „O mnie”, kontakcie, usługach i pozostałych stronach: podmiana i kadr każdego miejsca.'],
                ['label' => 'Google', 'route' => 'admin.seo-pages.edit', 'active' => ['admin.seo-pages.*'], 'description' => 'Tytuł i opis każdej strony stałej w wynikach wyszukiwania.'],
                ['label' => 'Maile', 'route' => 'admin.mail-texts.edit', 'active' => ['admin.mail-texts.*'], 'description' => 'Temat, pierwsze i ostatnie zdanie maili do klientek, podpis i stopka — z podglądem.'],
                ['label' => 'Certyfikat unikatu', 'route' => 'admin.certificate.edit', 'active' => ['admin.certificate.*'], 'description' => 'Nagłówek, cytat i dopisek pod logo na karcie do paczki — z podglądem PDF.'],
            ]],
            ['label' => 'Ustawienia', 'sections' => [
                ['label' => 'Ustawienia', 'route' => 'admin.settings.edit', 'active' => ['admin.settings.*'], 'description' => 'E-mail, próg darmowej wysyłki i kod Paczkomatu.'],
                ['label' => 'Konta', 'route' => 'admin.accounts.index', 'active' => ['admin.accounts.*'], 'description' => 'Kto ma dostęp do panelu: Ty i osoba, która pomaga przy zamówieniach.'],
                ['label' => 'Niewysłane maile', 'route' => 'admin.failed-mails.index', 'active' => ['admin.failed-mails.*'], 'description' => 'Maile ze sklepu, które nie wyszły mimo kilku prób. Wyślesz je jeszcze raz jednym kliknięciem.',
                    'count' => fn () => app(FailedMails::class)->all()->count(),
                    'count_label' => 'do ponowienia'],
            ]],
        ];
    }
}
