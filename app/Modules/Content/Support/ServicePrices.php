<?php

namespace App\Modules\Content\Support;

use App\Modules\Content\Enums\Service;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\OfferPrices;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A service's price list from the panel. A row without a name or a price does not show on the site, and a reduced
 * price comes with the lowest price from the 30 days before it. Rows have no code, so the history follows the name.
 */
class ServicePrices
{
    public function __construct(private Settings $settings, private OfferPrices $offerPrices) {}

    /**
     * Which row of the price list a price in the history belongs to.
     *
     * @param  array<string, mixed>  $row
     */
    public static function key(array $row): string
    {
        return Str::slug((string) ($row['label'] ?? ''), '_');
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
     * @return Collection<int, array{label: string, note: ?string, price_gross: int, compare_at_price: ?int, was: ?int, lowest: ?int}>
     */
    public function for(Service $service): Collection
    {
        $rows = collect((array) $this->settings->get($service->setting('prices'), []))
            ->filter(fn (mixed $row) => is_array($row) && filled($row['label'] ?? null) && is_numeric($row['price_gross'] ?? null))
            ->map(fn (array $row) => [
                'label' => (string) $row['label'],
                'note' => filled($row['note'] ?? null) ? (string) $row['note'] : null,
                'price_gross' => (int) $row['price_gross'],
                'compare_at_price' => is_numeric($row['compare_at_price'] ?? null) ? (int) $row['compare_at_price'] : null,
            ])
            ->values();

        return $this->offerPrices->withReductions($service->setting('prices'), $rows, self::key(...));
    }
}
