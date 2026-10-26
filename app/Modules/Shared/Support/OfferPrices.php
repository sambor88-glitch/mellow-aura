<?php

namespace App\Modules\Shared\Support;

use App\Modules\Shared\Models\OfferPriceHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Price history for the offers in the panel's price lists — workshops, firing, services — so a reduced price can
 * show the lowest price from the 30 days before the reduction, as product sizes do. An offer is its list and row,
 * e.g. „workshop_types:lepienie_z_reki”.
 */
class OfferPrices
{
    /**
     * Records the prices of a list that was just saved. An offer seen for the first time first gets the price it had
     * before the save, so a reduction made in that same save already has an earlier price to compare with.
     *
     * @param  array<string, int>  $before  row key => price before the save
     * @param  array<string, int>  $after  row key => price after the save
     */
    public function record(string $list, array $before, array $after): void
    {
        $latest = $this->history($list, array_keys($after))->map(fn (Collection $rows) => $rows->last()->price_gross);

        foreach ($after as $key => $price) {
            $last = $latest->get((string) $key);

            if ($last === null && isset($before[$key]) && $before[$key] !== $price) {
                $this->insert($list, (string) $key, $before[$key]);
                $last = $before[$key];
            }

            if ($last !== $price) {
                $this->insert($list, (string) $key, $price);
            }
        }
    }

    /**
     * Adds to every row of a price list what a reduced price needs on the page: `was`, the price before the
     * reduction, and `lowest`, the lowest price from the 30 days before it. Both stay null unless the row costs
     * less than its price before the reduction and the history knows an earlier price — only then may the page
     * cross a price out.
     *
     * @param  Collection<int, array<string, mixed>>  $rows  with price_gross and compare_at_price
     * @param  callable(array<string, mixed>): string  $key
     * @return Collection<int, array<string, mixed>>
     */
    public function withReductions(string $list, Collection $rows, callable $key): Collection
    {
        $isReduced = fn (array $row) => is_int($row['compare_at_price'] ?? null) && $row['compare_at_price'] > $row['price_gross'];
        $reduced = $rows->filter($isReduced);
        $history = $reduced->isEmpty() ? collect() : $this->history($list, $reduced->map($key)->all());

        return $rows->map(function (array $row) use ($history, $key, $isReduced) {
            $offer = $history->get($key($row));
            // A price set outside the panel has no row in the history, so nothing tells what came before it.
            $lowest = $isReduced($row) && $offer?->last()->price_gross === $row['price_gross'] ? LowestPrice::beforeCurrent($offer) : null;

            return [...$row, 'was' => $lowest === null ? null : $row['compare_at_price'], 'lowest' => $lowest];
        });
    }

    /**
     * @param  list<string>  $keys
     * @return Collection<string, Collection<int, OfferPriceHistory>>
     */
    private function history(string $list, array $keys): Collection
    {
        return OfferPriceHistory::query()
            ->whereIn('offer', array_map(fn (int|string $key) => $list.':'.$key, $keys))
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (OfferPriceHistory $row) => Str::after($row->offer, $list.':'));
    }

    private function insert(string $list, string $key, int $price): void
    {
        OfferPriceHistory::query()->create(['offer' => $list.':'.$key, 'price_gross' => $price, 'valid_from' => now()]);
    }
}
