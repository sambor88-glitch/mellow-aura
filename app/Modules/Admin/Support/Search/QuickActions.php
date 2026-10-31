<?php

namespace App\Modules\Admin\Support\Search;

use App\Models\User;
use App\Modules\Admin\Support\PanelAccess;
use Illuminate\Support\Facades\Route;

/**
 * „Szybko” in the search: the things Kasia starts most often, one Enter away. They show before anything is typed
 * and are found by their words. Like the menu, an action shows once its screen has a route, and only to an account
 * that may open it.
 */
final class QuickActions implements SearchProvider
{
    /** @var list<array{title: string, detail: string, route: string, query?: array<string, string>, anchor?: string, words: string, icon: string}> */
    private const ACTIONS = [
        ['title' => 'Dodaj produkt', 'detail' => 'Oferta → Produkty', 'route' => 'admin.products.create', 'words' => 'nowy produkt dodaj wazon kubek', 'icon' => '+'],
        ['title' => 'Dodaj termin warsztatu', 'detail' => 'Oferta → Warsztaty', 'route' => 'admin.workshops.edit', 'anchor' => 'termin-workshop', 'words' => 'nowy termin warsztat terminarz kalendarz dodaj', 'icon' => '+'],
        ['title' => 'Zamówienia do wysłania', 'detail' => 'Sprzedaż → Zamówienia', 'route' => 'admin.orders.index', 'words' => 'wysylka paczka do wyslania w realizacji', 'icon' => '→'],
        ['title' => 'Nieopłacone zamówienia', 'detail' => 'Sprzedaż → Zamówienia', 'route' => 'admin.orders.index', 'query' => ['platnosc' => 'nieoplacone'], 'words' => 'nieoplacone przelew czeka na wplate platnosc', 'icon' => '→'],
    ];

    public function group(): string
    {
        return 'Szybko';
    }

    public function screen(): string
    {
        return 'admin.dashboard';
    }

    public function search(string $term, int $limit): iterable
    {
        $found = array_filter($this->available(), fn (array $action) => PanelSearch::matches($term, $action['title'], $action['words']));

        return array_map($this->result(...), array_slice(array_values($found), 0, $limit));
    }

    /**
     * What the search shows before anything is typed.
     *
     * @return list<array{title: string, url: string, detail: ?string, meta: ?string, image: ?string, icon: ?string}>
     */
    public function all(?User $user): array
    {
        return array_values(array_map(
            fn (array $action) => $this->result($action)->toArray(),
            array_filter($this->available(), fn (array $action) => PanelAccess::allows($user, $action['route'])),
        ));
    }

    /** @return list<array{title: string, detail: string, route: string, query?: array<string, string>, anchor?: string, words: string, icon: string}> */
    private function available(): array
    {
        return array_values(array_filter(self::ACTIONS, fn (array $action) => Route::has($action['route'])));
    }

    /** @param  array{title: string, detail: string, route: string, query?: array<string, string>, anchor?: string, words: string, icon: string}  $action */
    private function result(array $action): SearchResult
    {
        $url = route($action['route'], $action['query'] ?? []).(isset($action['anchor']) ? '#'.$action['anchor'] : '');

        return new SearchResult(title: $action['title'], url: $url, route: $action['route'], detail: $action['detail'], icon: $action['icon']);
    }
}
