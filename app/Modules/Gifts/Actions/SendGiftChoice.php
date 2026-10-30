<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Gifts\Mail\GiftChoiceArrived;
use App\Modules\Gifts\Mail\GiftChoicesIssued;
use App\Modules\Gifts\Models\GiftChoice;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a gift choice card (MA-235): one that goes by e-mail to the person it is for, a printed one to the buyer
 * again as a PDF. The panel's „Wyślij jeszcze raz” and the day a card goes out both come here. A mail that cannot be
 * queued is reported and never stops the rest: the card stays in the panel, where Kasia sends it again.
 */
class SendGiftChoice
{
    public function __invoke(GiftChoice $choice): void
    {
        if ($choice->goesByEmail()) {
            rescue(fn () => Mail::to(new Address($choice->recipient_email, (string) $choice->recipient_name))->send(new GiftChoiceArrived($choice)));
            $choice->update(['sent_at' => $choice->sent_at ?? now()]);

            return;
        }

        $order = $choice->orderItem->order;
        rescue(fn () => Mail::to(new Address($order->email, $order->name))->send(new GiftChoicesIssued($order, collect([$choice]))));
    }
}
