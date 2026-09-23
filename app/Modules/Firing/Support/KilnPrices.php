<?php

namespace App\Modules\Firing\Support;

use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\OfferPrices;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The firing price list from the panel (kiln_prices). A row without a name or a price does not show on the site.
 * A reduced price comes with the lowest price from the 30 days before it.
 */
class KilnPrices
{
    public const LIST = 'kiln_prices';

    public function __construct(private Settings $settings, private OfferPrices $offerPrices) {}

    /**
     * Which row of the price list a price in the history belongs to.
     *
     * @param  array<string, mixed>  $row
     */
    public static function key(array $row): string
    {
        return filled($row['code'] ?? null) ? (string) $row['code'] : Str::slug((string) ($row['label'] ?? ''), '_');
    }

    /**
     * Row key => price, for the price history.
     *
     * @param  array<int, mixed>  $rows
     * @return array<string, int>
     */
    public static function prices(array $rows): array
    {
        return collect($rows)
            ->filter(fn (mixed $row) => is_array($row) && filled($row['label'] ?? null) && is_numeric($row['price_gross'] ?? null))
            ->mapWithKeys(fn (array $row) => [self::key($row) => (int) $row['price_gross']])
            ->all();
    }

    /**
     * @return Collection<int, array{code: ?string, label: string, note: ?string, price_gross: int, compare_at_price: ?int, was: ?int, lowest: ?int, unit: ?string, unit_label: ?string}>
     */
    public function all(): Collection
    {
        $rows = collect((array) $this->settings->get(self::LIST, []))
            ->filter(fn (mixed $row) => is_array($row) && filled($row['label'] ?? null) && is_numeric($row['price_gross'] ?? null))
            ->map(fn (array $row) => [
                'code' => $row['code'] ?? null,
                'label' => (string) $row['label'],
                'note' => filled($row['note'] ?? null) ? (string) $row['note'] : null,
                'price_gross' => (int) $row['price_gross'],
                'compare_at_price' => is_numeric($row['compare_at_price'] ?? null) ? (int) $row['compare_at_price'] : null,
                'unit' => $row['unit'] ?? null,
                'unit_label' => filled($row['unit_label'] ?? null) ? (string) $row['unit_label'] : null,
            ])
            ->values();

        return $this->offerPrices->withReductions(self::LIST, $rows, self::key(...));
    }
}
