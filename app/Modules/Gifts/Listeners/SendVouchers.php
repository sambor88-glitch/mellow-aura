<?php

namespace App\Modules\Gifts\Listeners;

use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Gifts\Mail\VouchersIssued;
use App\Modules\Gifts\Models\Voucher;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the buyer the vouchers issued with the payment, as PDFs. It runs within the request until
 * a queue worker exists (MA-80); a mail that fails is reported and never undoes a paid order —
 * the vouchers are already issued and Kasia can download them in the panel.
 */
class SendVouchers
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        $vouchers = Voucher::query()
            ->with('orderItem')
            ->whereIn('order_item_id', $order->items()->select('id'))
            ->orderBy('id')
            ->get();

        if ($vouchers->isNotEmpty()) {
            rescue(fn () => Mail::to(new Address($order->email, $order->name))->send(new VouchersIssued($order, $vouchers)));
        }
    }
}
