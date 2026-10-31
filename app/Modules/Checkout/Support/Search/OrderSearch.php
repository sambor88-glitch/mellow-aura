<?php

namespace App\Modules\Checkout\Support\Search;

use App\Modules\Admin\Support\Search\PanelSearch;
use App\Modules\Admin\Support\Search\SearchProvider;
use App\Modules\Admin\Support\Search\SearchResult;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;

/**
 * Orders in the panel search (MA-308), by number, the customer's name and e-mail, the newest first.
 * „1047” finds MA-2026-1047. A helper sees them too: the orders are her screens.
 */
final class OrderSearch implements SearchProvider
{
    public function group(): string
    {
        return 'Zamówienia';
    }

    public function screen(): string
    {
        return 'admin.orders.index';
    }

    public function search(string $term, int $limit): iterable
    {
        $query = Order::query()->latest('id');

        return PanelSearch::whereWords($query, $term, ['number', 'name', 'email'])
            ->limit($limit * 4)
            ->get()
            ->filter(fn (Order $order) => PanelSearch::matches($term, $order->number, $order->name, $order->email))
            ->take($limit)
            ->map(fn (Order $order) => new SearchResult(
                title: (string) $order->number,
                url: route('admin.orders.show', $order),
                route: 'admin.orders.show',
                detail: collect([
                    $order->name,
                    $order->payment_status === PaymentStatus::Paid ? $order->status->label() : $order->payment_status->label(),
                    $order->created_at?->format('j.m.Y'),
                ])->filter()->join(' · '),
                meta: $order->money($order->total_gross),
                icon: '№',
            ))
            ->values();
    }
}
