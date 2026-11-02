<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Enums\SalesPeriod;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Workshops\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The sales summary in the panel (MA-344): what came in over a period and what sold best, in each currency on its own —
 * złoty and euro are never added up.
 *
 * An order counts on the day it was paid. Its revenue is what came in through the gateway or by transfer (the part
 * a voucher paid came in when the voucher was sold) less the refunds the panel knows of: the pieces someone else bought
 * first (missing_quantity) and a paid workshop booking cancelled or overbooked. A refund counts against the order it
 * returns, so a day never goes below zero. Refunds after a withdrawal or a complaint are made by hand and have no amount
 * in the panel yet, so they are not taken off.
 */
class SalesSummary
{
    /** How many products the list of best sellers shows. */
    public const TOP = 5;

    /**
     * @return array{
     *     period: SalesPeriod,
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     currencies: list<array{
     *         currency: string,
     *         current: array<string, mixed>,
     *         previous: array<string, mixed>,
     *         chart: list<array{label: string, long: string, amount: int}>,
     *     }>,
     * }
     */
    public function for(SalesPeriod $period, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        [$from, $to] = $period->range($now);
        [$previousFrom, $previousTo] = $period->previousRange($now);

        $current = $this->orders($from, $to)->groupBy(fn (Order $order) => $order->currency ?? 'PLN');
        $previous = $this->orders($previousFrom, $previousTo)->groupBy(fn (Order $order) => $order->currency ?? 'PLN');

        // Złoty first, then any other currency that sold in either period.
        $currencies = $current->keys()->merge($previous->keys())->unique()
            ->sortBy(fn (string $currency) => $currency === 'PLN' ? '' : $currency)
            ->values();

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'currencies' => $currencies->map(fn (string $currency) => [
                'currency' => $currency,
                'current' => $this->figures($current->get($currency, collect())),
                'previous' => $this->figures($previous->get($currency, collect())),
                'chart' => $this->chart($current->get($currency, collect()), $from, $to, $period->byWeek()),
            ])->all(),
        ];
    }

    /**
     * @return Collection<int, Order>
     */
    private function orders(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<', $to)
            ->with(['items.variant.product.category', 'workshopBooking', 'customOrderPayment'])
            ->orderBy('paid_at')
            ->get();
    }

    /**
     * @param  Collection<int, Order>  $orders  orders of one currency
     * @return array<string, mixed>
     */
    private function figures(Collection $orders): array
    {
        $paid = (int) $orders->sum(fn (Order $order) => $order->amountDue());
        $refunds = (int) $orders->sum(fn (Order $order) => $this->refund($order));
        $count = $orders->count();

        $workshops = $orders->filter(fn (Order $order) => $order->workshopBooking !== null);
        $custom = $orders->filter(fn (Order $order) => $order->workshopBooking === null && $order->customOrderPayment !== null);
        $shopItems = $orders
            ->filter(fn (Order $order) => $order->workshopBooking === null && $order->customOrderPayment === null)
            ->flatMap(fn (Order $order) => $order->items);
        [$vouchers, $products] = $shopItems->partition(fn (OrderItem $item) => $item->variant?->product?->isVoucher() === true);

        return [
            'revenue' => $paid - $refunds,
            'paid' => $paid,
            'refunds' => $refunds,
            'orders' => $count,
            // The basket with its delivery, also the part a voucher paid for.
            'average' => $count > 0 ? intdiv((int) $orders->sum('total_gross'), $count) : 0,
            'shipping' => (int) $orders->sum('shipping_gross'),
            'top' => $this->bestSellers($products),
            'workshops' => [
                'count' => (int) $workshops->sum(fn (Order $order) => $order->workshopBooking->seats),
                'amount' => (int) $workshops->sum(fn (Order $order) => $order->amountDue() - $this->refund($order)),
            ],
            'vouchers' => [
                'count' => (int) $vouchers->sum(fn (OrderItem $item) => $item->quantity - $item->missing_quantity),
                'amount' => (int) $vouchers->sum(fn (OrderItem $item) => $item->unit_price_gross * ($item->quantity - $item->missing_quantity)),
            ],
            'custom' => [
                'count' => $custom->count(),
                'amount' => (int) $custom->sum(fn (Order $order) => $order->amountDue() - $this->refund($order)),
            ],
        ];
    }

    /**
     * What goes back of this order's payment, by what the panel knows. Never more than came in.
     */
    private function refund(Order $order): int
    {
        if (in_array($order->workshopBooking?->status, [BookingStatus::Cancelled, BookingStatus::Overbooked], true)) {
            return $order->amountDue();
        }

        $missing = $order->items->filter(fn (OrderItem $item) => $item->missing_quantity > 0);

        if ($missing->isEmpty()) {
            return 0;
        }

        // The same reading as the order e-mails (OrderSummary): with nothing left to send, the whole payment goes back.
        $toSend = $order->items->filter(fn (OrderItem $item) => $item->quantity > $item->missing_quantity && ($item->product_variant_id !== null || $item->custom_text !== null));

        $refund = $toSend->isEmpty()
            ? $order->total_gross
            : (int) $missing->sum(fn (OrderItem $item) => $item->unit_price_gross * $item->missing_quantity);

        return min($refund, $order->amountDue());
    }

    /**
     * The products that sold most pieces, then most money. A mug from the configurator has no product, so it counts
     * under its name; gift wrapping is not a product and is left out.
     *
     * @param  Collection<int, OrderItem>  $items
     * @return list<array{name: string, pieces: int, amount: int}>
     */
    private function bestSellers(Collection $items): array
    {
        return $items
            ->filter(fn (OrderItem $item) => ($item->product_variant_id !== null || $item->custom_text !== null) && $item->quantity > $item->missing_quantity)
            ->groupBy(fn (OrderItem $item) => $item->variant !== null ? 'product:'.$item->variant->product_id : 'name:'.$item->product_name)
            ->map(fn (Collection $group) => [
                // The name from the latest sale, as Kasia calls the product now.
                'name' => $group->last()->product_name,
                'pieces' => (int) $group->sum(fn (OrderItem $item) => $item->quantity - $item->missing_quantity),
                'amount' => (int) $group->sum(fn (OrderItem $item) => $item->unit_price_gross * ($item->quantity - $item->missing_quantity)),
            ])
            ->sort(fn (array $a, array $b) => [$b['pieces'], $b['amount'], $a['name']] <=> [$a['pieces'], $a['amount'], $b['name']])
            ->take(self::TOP)
            ->values()
            ->all();
    }

    /**
     * Revenue day by day, or week by week from the start of the period, with every day or week in it, also the empty ones.
     *
     * @param  Collection<int, Order>  $orders
     * @return list<array{label: string, long: string, amount: int}>
     */
    private function chart(Collection $orders, CarbonImmutable $from, CarbonImmutable $to, bool $byWeek): array
    {
        $step = $byWeek ? 7 : 1;
        $buckets = [];

        for ($start = $from->startOfDay(); $start < $to; $start = $start->addDays($step)) {
            $end = $start->addDays($step)->min($to);
            $buckets[] = [
                'label' => $start->format('j.m'),
                'long' => $byWeek
                    ? $start->format('j.m').'–'.$end->subSecond()->format('j.m')
                    : $start->locale('pl')->translatedFormat('l, j F'),
                'amount' => (int) $orders
                    ->filter(fn (Order $order) => $order->paid_at >= $start && $order->paid_at < $end)
                    ->sum(fn (Order $order) => $order->amountDue() - $this->refund($order)),
            ];
        }

        return $buckets;
    }

    /**
     * The change against the period before, for the line under a number: „↑ 12%”, „↓ 5%”. Fewer sales are no alarm,
     * so only the arrow says which way it went.
     */
    public static function change(int $now, int $before): string
    {
        if ($before === 0) {
            return $now === 0 ? 'bez zmian' : 'wcześniej 0';
        }

        $percent = (int) round(($now - $before) * 100 / $before);

        return match (true) {
            $percent > 0 => '↑ '.$percent.'%',
            $percent < 0 => '↓ '.abs($percent).'%',
            default => 'bez zmian',
        };
    }

    /**
     * The top of the chart's axis: a round amount just above the best day, so the ticks read 0, 750 zł, 1500 zł.
     */
    public static function axisTop(int $max): int
    {
        if ($max <= 0) {
            return 0;
        }

        $magnitude = 10 ** (strlen((string) intdiv($max - 1, 100)) - 1) * 100;

        foreach ([1, 2, 5, 10] as $step) {
            if ($step * $magnitude >= $max) {
                return $step * $magnitude;
            }
        }

        return 10 * $magnitude;
    }
}
