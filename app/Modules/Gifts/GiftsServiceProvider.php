<?php

namespace App\Modules\Gifts;

use App\Modules\Cart\LineTypes;
use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Gifts\Cart\BundleLine;
use App\Modules\Gifts\Cart\BundleLines;
use App\Modules\Gifts\Cart\GiftWrapLine;
use App\Modules\Gifts\Cart\GiftWrapLines;
use App\Modules\Gifts\Cart\VoucherLine;
use App\Modules\Gifts\Cart\VoucherLines;
use App\Modules\Gifts\Listeners\SendVouchers;
use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\SitemapPages;
use Illuminate\Support\Facades\Event;

class GiftsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(LineTypes::class, fn (LineTypes $types) => $types
            ->register(VoucherLine::TYPE, VoucherLines::class)
            ->register(BundleLine::TYPE, BundleLines::class)
            ->register(GiftWrapLine::TYPE, GiftWrapLines::class));
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->routes('gifts.index', 'bundles.index', 'vouchers.index'));
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(OrderPaid::class, SendVouchers::class);
    }
}
