<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\Admin\Support\Push\PanelNotice;
use App\Modules\Admin\Support\Push\PanelPush;
use App\Modules\Checkout\Events\OrderPaid;

/**
 * Rings Kasia's phone about a paid order (MA-345): „Nowe zamówienie MA-2026-1234 · 255 zł”. Only the number and
 * the amount — the notification shows on a locked screen. A workshop booking rings from Workshops with its day.
 * Failing to queue it is reported and never undoes the payment.
 */
class NotifyPanelAboutOrder
{
    public function __construct(private PanelPush $push) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        if ($order->workshopBooking()->exists()) {
            return;
        }

        $deposit = $order->customOrderPayment()->exists();

        rescue(fn () => $this->push->notify(new PanelNotice(
            title: ($deposit ? 'Wpłata za zamówienie indywidualne ' : 'Nowe zamówienie ').$order->number.' · '.$order->money($order->total_gross),
            body: $deposit ? 'Opłacone. Dotknij, żeby otworzyć zamówienie.' : 'Opłacone — czeka na spakowanie.',
            url: route('admin.orders.show', $order),
            route: 'admin.orders.show',
            tag: 'order-'.$order->id,
        )));
    }
}
