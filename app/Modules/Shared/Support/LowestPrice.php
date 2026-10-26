<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Collection;

/**
 * The lowest price in effect during the 30 days before the current price took effect. The price information
 * act (art. 4) wants it next to every announced reduction, for goods and for services alike.
 */
final class LowestPrice
{
    /**
     * @param  Collection<int, object>  $history  rows with price_gross and valid_from, oldest first; the last one is the current price
     * @return int|null null without an earlier price to compare with — then no crossed-out price may show
     */
    public static function beforeCurrent(Collection $history): ?int
    {
        $history = $history->values();
        $current = $history->pop();

        if ($current === null || $history->isEmpty()) {
            return null;
        }

        $windowStart = $current->valid_from->copy()->subDays(30);
        $inEffectAtStart = $history->filter(fn (object $row) => $row->valid_from->lt($windowStart))->last();
        $changedInWindow = $history->filter(fn (object $row) => $row->valid_from->gte($windowStart));

        return $changedInWindow->push($inEffectAtStart)->filter()->min('price_gross');
    }
}
