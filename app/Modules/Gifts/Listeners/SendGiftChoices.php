<?php

namespace App\Modules\Gifts\Listeners;

use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Gifts\Actions\SendGiftChoice;
use App\Modules\Gifts\Mail\GiftChoicesIssued;
use App\Modules\Gifts\Models\GiftChoice;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the gift choice cards issued with the payment (MA-235): all of them to the buyer as PDFs, and a card
 * whose day is today to the person it is for at once. Later days are for SendDueGiftChoices. A mail that cannot
 * be queued is reported and never undoes a paid order.
 */
class SendGiftChoices
{
    public function __construct(private SendGiftChoice $send) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        $choices = GiftChoice::query()
            ->issued()
            ->whereIn('order_item_id', $order->items()->select('id'))
            ->orderBy('id')
            ->get();

        if ($choices->isEmpty()) {
            return;
        }

        rescue(fn () => Mail::to(new Address($order->email, $order->name))->send(new GiftChoicesIssued($order, $choices)));

        $choices
            ->filter(fn (GiftChoice $choice) => $choice->goesByEmail() && $choice->sent_at === null && ! $choice->send_on->isFuture())
            ->each(fn (GiftChoice $choice) => ($this->send)($choice));
    }
}
