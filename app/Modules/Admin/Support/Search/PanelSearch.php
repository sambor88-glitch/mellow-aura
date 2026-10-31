<?php

namespace App\Modules\Admin\Support\Search;

use App\Models\User;
use App\Modules\Admin\Support\PanelAccess;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Str;

/**
 * The search at the top of every panel screen (MA-308, Ctrl+K). Modules add their providers in their service providers
 * (callAfterResolving), the way they add pages to the sitemap; this class asks the ones the account may open and puts
 * the results in groups, in a fixed order, at most five in a group.
 *
 * MySQL LIKE, no Scout. The words are found in any order and without Polish letters: „prog” finds „Próg”. The database's
 * collation already treats ó as o and ż as z, but not ł as l, so a query asks with „_” in place of l and the results
 * are checked again here (matches()).
 */
final class PanelSearch
{
    public const LIMIT = 5;

    /** The groups in the order they show. */
    public const GROUPS = ['Produkty', 'Zamówienia', 'Klienci', 'Warsztaty', 'Ustawienia', 'Ekrany', 'Szybko'];

    /** Shorter than this, the search waits for more letters. */
    public const MIN_LENGTH = 2;

    /** @var list<SearchProvider> */
    private array $providers = [];

    public function add(SearchProvider $provider): static
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * @return list<array{label: string, results: list<array{title: string, url: string, detail: ?string, meta: ?string, image: ?string, icon: ?string}>}>
     */
    public function run(?User $user, string $term): array
    {
        $term = trim(preg_replace('/\s+/u', ' ', $term) ?? '');

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $found = array_fill_keys(self::GROUPS, []);

        foreach ($this->providers as $provider) {
            if (! PanelAccess::allows($user, $provider->screen())) {
                continue;
            }

            foreach ($provider->search($term, self::LIMIT) as $result) {
                if (PanelAccess::allows($user, $result->route)) {
                    $found[$provider->group()][] = $result->toArray();
                }
            }
        }

        $groups = [];

        foreach ($found as $label => $results) {
            if ($results !== []) {
                $groups[] = ['label' => $label, 'results' => array_slice($results, 0, self::LIMIT)];
            }
        }

        return $groups;
    }

    /** Lower case without Polish letters: „Próg Łódź” → „prog lodz”. */
    public static function fold(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    /** Whether every word of $term is in one of $texts, without Polish letters and case. */
    public static function matches(string $term, ?string ...$texts): bool
    {
        $haystack = self::fold(implode(' ', array_filter($texts, fn (?string $text) => filled($text))));

        foreach (self::words($term) as $word) {
            if (! str_contains($haystack, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Narrows a query to the rows where every word of $term is in one of $columns. It may let through a few rows
     * too many (an l can stand for ł); check them with matches().
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @param  list<string>  $columns
     * @return TQuery
     */
    public static function whereWords(Builder $query, string $term, array $columns): Builder
    {
        foreach (self::words($term) as $word) {
            $pattern = '%'.str_replace('l', '_', addcslashes($word, '%_\\')).'%';

            $query->where(function (Builder $query) use ($columns, $pattern) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', $pattern);
                }
            });
        }

        return $query;
    }

    /** @return list<string> */
    private static function words(string $term): array
    {
        return array_values(array_filter(explode(' ', self::fold(trim($term))), fn (string $word) => $word !== ''));
    }
}
