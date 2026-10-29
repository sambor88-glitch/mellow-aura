<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\DispatchTime;
use Carbon\CarbonInterface;

/**
 * How long a paid order has waited in the studio, and whether it waited past the dispatch time from the panel (MA-250).
 * The wait is counted in calendar days, as Kasia says it; the deadline in working days, as the shop promises it.
 */
class OrderWait
{
    public function __construct(private readonly Settings $settings) {}

    public function since(Order $order): CarbonInterface
    {
        return $order->paid_at ?? $order->created_at;
    }

    public function days(Order $order): int
    {
        return (int) $this->since($order)->copy()->startOfDay()->diffInDays(today());
    }

    /** „od dziś”, „czeka 1 dzień”, „czeka 6 dni”. */
    public function label(Order $order): string
    {
        return match ($days = $this->days($order)) {
            0 => 'od dziś',
            1 => 'czeka 1 dzień',
            default => 'czeka '.$days.' dni',
        };
    }

    /** The last day the shop promised to send it: the most working days from the panel after payment. */
    public function deadline(Order $order): ?CarbonInterface
    {
        $days = DispatchTime::days($this->settings);

        return $days === null ? null : $this->since($order)->copy()->addWeekdays($days[1])->endOfDay();
    }

    /** Only an order still in the studio can be late. */
    public function overdue(Order $order): bool
    {
        return $order->status === OrderStatus::InProgress && ($deadline = $this->deadline($order)) !== null && now()->greaterThan($deadline);
    }
}
