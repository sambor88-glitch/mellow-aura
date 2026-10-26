<?php

namespace App\Modules\Workshops\Support;

use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\OfferPrices;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The workshop price list from the panel (workshop_types). A row without a name or a price
 * is waiting to be filled in and does not show on the site. A reduced price comes with the lowest
 * price from the 30 days before it.
 */
class WorkshopTypes
{
    public const LIST = 'workshop_types';

    public function __construct(private Settings $settings, private OfferPrices $offerPrices) {}

    /**
     * Which row of the price list a price in the history belongs to.
     *
     * @param  array<string, mixed>  $row
     */
    public static function key(array $row): string
    {
        return filled($row['code'] ?? null) ? (string) $row['code'] : Str::slug((string) ($row['name'] ?? ''), '_');
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
            ->filter(fn (mixed $row) => is_array($row) && filled($row['name'] ?? null) && is_numeric($row['price_gross'] ?? null))
            ->mapWithKeys(fn (array $row) => [self::key($row) => (int) $row['price_gross']])
            ->all();
    }

    /**
     * @return Collection<int, array{code: ?string, name: string, price_gross: int, compare_at_price: ?int, was: ?int, lowest: ?int, unit: ?string, unit_label: ?string, duration_label: ?string, group_label: ?string, summary: ?string, includes: list<string>}>
     */
    public function all(): Collection
    {
        $rows = collect((array) $this->settings->get(self::LIST, []))
            ->filter(fn (mixed $type) => is_array($type) && filled($type['name'] ?? null) && is_numeric($type['price_gross'] ?? null))
            ->map(fn (array $type) => [
                'code' => $type['code'] ?? null,
                'name' => (string) $type['name'],
                'price_gross' => (int) $type['price_gross'],
                'compare_at_price' => is_numeric($type['compare_at_price'] ?? null) ? (int) $type['compare_at_price'] : null,
                'unit' => $type['unit'] ?? null,
                'unit_label' => filled($type['unit_label'] ?? null) ? (string) $type['unit_label'] : null,
                'duration_label' => filled($type['duration_label'] ?? null) ? (string) $type['duration_label'] : null,
                'group_label' => filled($type['group_label'] ?? null) ? (string) $type['group_label'] : null,
                'summary' => filled($type['summary'] ?? null) ? (string) $type['summary'] : null,
                'includes' => array_values(array_filter((array) ($type['includes'] ?? []), fn (mixed $line) => is_string($line) && trim($line) !== '')),
            ])
            ->values();

        return $this->offerPrices->withReductions(self::LIST, $rows, self::key(...));
    }
}
