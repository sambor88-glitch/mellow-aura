<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Mail\BackInStock;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StockNotification;
use App\Modules\Localization\Support\Locales;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the people waiting for a size that it is back on the shelf (MA-266), each in the language she signed up
 * in, and forgets her address: the consent was for this one mail. The mail carries copies of the name and the
 * link, not the row, so it still goes out from the queue after the row is gone; if it never does, it waits in
 * „Niewysłane maile”.
 *
 * Someone waiting on a page the product no longer has — hidden (the product or the size), not written in her
 * language, no price in her currency — keeps waiting: a link to a page that is not there would waste her one mail.
 */
class SendRestockNotices
{
    /**
     * @return int how many mails were queued
     */
    public function __invoke(ProductVariant $variant): int
    {
        $variant->refresh()->load('product');

        if (! $variant->isInStock() || $variant->is_hidden || ! $variant->product->is_published) {
            return 0;
        }

        $sent = 0;

        foreach ($variant->stockNotifications()->get()->groupBy('locale') as $locale => $notifications) {
            if (! Locales::isEnabled($locale) || ! $variant->product->availableIn($locale) || $variant->price(Locales::currency($locale)) === null) {
                continue;
            }

            [$name, $label, $url] = Locales::within($locale, fn () => [
                $variant->product->name,
                $variant->label,
                route('product.show', $variant->product).'?wariant='.$variant->id,
            ]);

            $notifications->each(function (StockNotification $notification) use ($name, $label, $url, &$sent) {
                Mail::to($notification->email)->queue(new BackInStock($notification->locale, $name, $label, $url, $notification->email));
                $notification->delete();
                $sent++;
            });
        }

        return $sent;
    }
}
