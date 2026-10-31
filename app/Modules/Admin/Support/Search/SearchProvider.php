<?php

namespace App\Modules\Admin\Support\Search;

/**
 * One module's part of the panel search (MA-308). A module registers its provider in its own service provider
 * and searches only its own tables. PanelSearch asks it only for an account that may open its screen, and still drops
 * each result whose route that account may not open.
 */
interface SearchProvider
{
    /** The group the results show under: one of PanelSearch::GROUPS. */
    public function group(): string;

    /** The panel route of the screen the results belong to, checked with PanelAccess. */
    public function screen(): string;

    /**
     * At most $limit results for the words in $term, the best first. $term is trimmed and at least two characters long.
     *
     * @return iterable<SearchResult>
     */
    public function search(string $term, int $limit): iterable;
}
