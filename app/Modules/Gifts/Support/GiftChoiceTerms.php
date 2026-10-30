<?php

namespace App\Modules\Gifts\Support;

use App\Modules\Settings\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The terms of a gift choice card from the panel (MA-235): how many days the person it is for has to choose, and
 * what the delivery to her costs, paid by the buyer with the card. Until Kasia sets the delivery, it costs what the
 * cheapest paid way of delivery costs in the shop.
 */
class GiftChoiceTerms
{
    public const DEFAULT_DAYS = 60;

    /** The reminders go this many days before the end, the first one first. */
    public const REMINDER_DAYS = [14, 3];

    /** The furthest day a card can be sent on. */
    public const MAX_SEND_DAYS = 365;

    public function __construct(private Settings $settings) {}

    public function days(): int
    {
        return min(365, max(7, (int) $this->settings->get('gift_choice_days', self::DEFAULT_DAYS)));
    }

    /** In grosze. */
    public function deliveryGross(): int
    {
        $set = $this->settings->get('gift_choice_delivery_gross');

        if (is_numeric($set)) {
            return max(0, (int) $set);
        }

        return (int) collect((array) $this->settings->get('shipping_methods', []))
            ->map(fn (mixed $method) => is_array($method) ? (int) ($method['price_gross'] ?? 0) : 0)
            ->filter(fn (int $price) => $price > 0)
            ->min();
    }

    /**
     * The last day to choose: the days count from the payment, or from the day the card goes out if that is later.
     */
    public function chooseBy(CarbonInterface $paidAt, ?CarbonInterface $sendOn): CarbonImmutable
    {
        $start = CarbonImmutable::parse($sendOn !== null && $sendOn->greaterThan($paidAt) ? $sendOn : $paidAt)->startOfDay();

        return $start->addDays($this->days());
    }
}
