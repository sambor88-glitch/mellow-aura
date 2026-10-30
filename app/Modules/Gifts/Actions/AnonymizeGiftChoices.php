<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Gifts\Models\GiftChoice;

/**
 * Wipes a customer from the gift choice cards (MA-235) when she asks to be forgotten (GDPR art. 17, MA-241).
 * From the cards she bought go the names and the dedication; from a card that came to her by e-mail goes her
 * address. The code, the size and the dates stay: whoever holds the card can still choose. A card still waiting
 * for its day keeps the address it goes to — it belongs to the person it is for, not to the buyer.
 */
class AnonymizeGiftChoices
{
    /**
     * @param  iterable<int>  $orderItemIds  the order items she bought cards with
     * @return int how many cards were wiped
     */
    public function __invoke(iterable $orderItemIds, string $email): int
    {
        $bought = GiftChoice::query()
            ->whereIn('order_item_id', collect($orderItemIds)->all())
            ->update(['recipient_name' => null, 'sender_name' => null, 'dedication' => null]);

        $received = GiftChoice::query()
            ->where('recipient_email', $email)
            ->whereNotNull('sent_at')
            ->update(['recipient_email' => null]);

        return $bought + $received;
    }
}
