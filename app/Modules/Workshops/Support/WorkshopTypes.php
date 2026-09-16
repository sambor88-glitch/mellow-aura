<?php

namespace App\Modules\Workshops\Support;

use App\Modules\Settings\Settings;
use Illuminate\Support\Collection;

/**
 * The workshop price list from the panel (workshop_types). A row without a name or a price
 * is waiting to be filled in and does not show on the site.
 */
class WorkshopTypes
{
    public function __construct(private Settings $settings) {}

    /**
     * @return Collection<int, array{code: ?string, name: string, price_gross: int, unit: ?string, unit_label: ?string, duration_label: ?string, group_label: ?string, summary: ?string, includes: list<string>}>
     */
    public function all(): Collection
    {
        return collect((array) $this->settings->get('workshop_types', []))
            ->filter(fn (mixed $type) => is_array($type) && filled($type['name'] ?? null) && is_numeric($type['price_gross'] ?? null))
            ->map(fn (array $type) => [
                'code' => $type['code'] ?? null,
                'name' => (string) $type['name'],
                'price_gross' => (int) $type['price_gross'],
                'unit' => $type['unit'] ?? null,
                'unit_label' => filled($type['unit_label'] ?? null) ? (string) $type['unit_label'] : null,
                'duration_label' => filled($type['duration_label'] ?? null) ? (string) $type['duration_label'] : null,
                'group_label' => filled($type['group_label'] ?? null) ? (string) $type['group_label'] : null,
                'summary' => filled($type['summary'] ?? null) ? (string) $type['summary'] : null,
                'includes' => array_values(array_filter((array) ($type['includes'] ?? []), fn (mixed $line) => is_string($line) && trim($line) !== '')),
            ])
            ->values();
    }
}
