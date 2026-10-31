<?php

namespace App\Modules\Admin\Support;

/**
 * Which tab of a long panel screen opens first (MA-315): the one the screen asks for (a mistake in its form), then the
 * one in the address (?tab=), then the first. The browser may still move to the tab with a mistake, to the one a #link
 * points into, or to the one used last on the screen (resources/js/panel-tabs.js).
 */
final class Tabs
{
    /**
     * @param  array<string, string|array{label: string, count?: int|string, note?: string, dot?: 'error'|'todo'|null}>  $tabs
     */
    public static function active(array $tabs, ?string $asked = null): string
    {
        $keys = array_map('strval', array_keys($tabs));
        $query = request()->query('tab');

        return match (true) {
            in_array($asked, $keys, true) => $asked,
            is_string($query) && in_array($query, $keys, true) => $query,
            default => $keys[0],
        };
    }
}
