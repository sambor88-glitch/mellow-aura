<?php

namespace App\Modules\Catalog\Support\Search;

use App\Modules\Admin\Support\Search\PanelSearch;
use App\Modules\Admin\Support\Search\SearchProvider;
use App\Modules\Admin\Support\Search\SearchResult;

/**
 * The fields of „Ustawienia produktów” in the panel search (MA-308): Enter opens the screen with the cursor in the field.
 */
final class ProductSettingsSearch implements SearchProvider
{
    /** Field id => [label, more words it is found by]. */
    private const FIELDS = [
        'hero-product' => ['Produkt na dużym zdjęciu strony głównej', 'hero glowna strona glowna duze zdjecie'],
        'hero-badge' => ['Etykieta na dużym zdjęciu strony głównej', 'nowosc hero glowna'],
        'euro-markup' => ['Narzut na ceny w euro', 'eur euro nbp kurs waluta angielski sklep'],
        'last-pieces' => ['Ostatnie sztuki na „Na dziś”', 'ostatnie sztuki ostatnia sztuka wyprzedane stan magazyn konczy sie'],
    ];

    public function group(): string
    {
        return 'Ustawienia';
    }

    public function screen(): string
    {
        return 'admin.products.settings';
    }

    public function search(string $term, int $limit): iterable
    {
        foreach (self::FIELDS as $id => [$label, $words]) {
            if (PanelSearch::matches($term, $label, $words, 'ustawienia produktow')) {
                yield new SearchResult(
                    title: $label,
                    url: route('admin.products.settings').'#'.$id,
                    route: 'admin.products.settings',
                    detail: 'Produkty → Ustawienia produktów',
                    icon: '⚙',
                );
            }
        }
    }
}
