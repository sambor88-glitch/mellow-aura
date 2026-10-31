<?php

namespace App\Modules\Admin\Support\Search;

use App\Modules\Admin\Menu;

/**
 * The sections of the menu, by their name, their group and what they hold: „zwrot” finds „Odstąpienia”.
 * Menu already leaves out the sections this account may not open.
 */
final class ScreenSearch implements SearchProvider
{
    public function group(): string
    {
        return 'Ekrany';
    }

    public function screen(): string
    {
        return 'admin.dashboard';
    }

    public function search(string $term, int $limit): iterable
    {
        $found = 0;

        foreach (Menu::groups() as $group) {
            foreach ($group['sections'] as $section) {
                if ($found < $limit && PanelSearch::matches($term, $section['label'], $group['label'], $section['description'])) {
                    $found++;

                    yield new SearchResult(
                        title: $section['label'],
                        url: route($section['route']),
                        route: $section['route'],
                        detail: $group['label'],
                        meta: $section['count'] ? $section['count'].' '.$section['count_label'] : null,
                        icon: '→',
                    );
                }
            }
        }
    }
}
