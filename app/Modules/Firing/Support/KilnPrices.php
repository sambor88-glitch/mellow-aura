<?php

namespace App\Modules\Firing\Support;

use App\Modules\Settings\Settings;
use Illuminate\Support\Collection;

/**
 * The firing price list from the panel (kiln_prices). A row without a name or a price does not show on the site.
 */
class KilnPrices
{
    public function __construct(private Settings $settings) {}

    /**
     * @return Collection<int, array{code: ?string, label: string, note: ?string, price_gross: int, unit: ?string, unit_label: ?string}>
     */
    public function all(): Collection
    {
        return collect((array) $this->settings->get('kiln_prices', []))
            ->filter(fn (mixed $row) => is_array($row) && filled($row['label'] ?? null) && is_numeric($row['price_gross'] ?? null))
            ->map(fn (array $row) => [
                'code' => $row['code'] ?? null,
                'label' => (string) $row['label'],
                'note' => filled($row['note'] ?? null) ? (string) $row['note'] : null,
                'price_gross' => (int) $row['price_gross'],
                'unit' => $row['unit'] ?? null,
                'unit_label' => filled($row['unit_label'] ?? null) ? (string) $row['unit_label'] : null,
            ])
            ->values();
    }
}
