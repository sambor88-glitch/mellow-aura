<?php

namespace App\Modules\Gifts;

use App\Modules\Cart\LineTypes;
use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Gifts\Cart\BundleLine;
use App\Modules\Gifts\Cart\BundleLines;
use App\Modules\Gifts\Cart\GiftChoiceLine;
use App\Modules\Gifts\Cart\GiftChoiceLines;
use App\Modules\Gifts\Cart\GiftWrapLine;
use App\Modules\Gifts\Cart\GiftWrapLines;
use App\Modules\Gifts\Cart\VoucherLine;
use App\Modules\Gifts\Cart\VoucherLines;
use App\Modules\Gifts\Console\ReleaseVoucherHoldsCommand;
use App\Modules\Gifts\Console\SendGiftChoicesCommand;
use App\Modules\Gifts\Listeners\SendGiftChoices;
use App\Modules\Gifts\Listeners\SendVouchers;
use App\Modules\Gifts\Mail\GiftChoiceArrived;
use App\Modules\Gifts\Mail\GiftChoiceReminder;
use App\Modules\Gifts\Mail\GiftChoicesIssued;
use App\Modules\Gifts\Mail\VouchersIssued;
use App\Modules\Gifts\Support\BundleMerchantItems;
use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\MerchantFeed;
use App\Modules\Shared\Support\SitemapPages;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

class GiftsServiceProvider extends ModuleServiceProvider
{
    protected array $customerMails = [
        VouchersIssued::class,
        GiftChoicesIssued::class,
        GiftChoiceArrived::class,
        GiftChoiceReminder::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(LineTypes::class, fn (LineTypes $types) => $types
            ->register(VoucherLine::TYPE, VoucherLines::class)
            ->register(BundleLine::TYPE, BundleLines::class)
            ->register(GiftWrapLine::TYPE, GiftWrapLines::class)
            ->register(GiftChoiceLine::TYPE, GiftChoiceLines::class));
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->routes('gifts.index', 'bundles.index', 'vouchers.index'));
        $this->callAfterResolving(MerchantFeed::class, fn (MerchantFeed $feed) => $feed->add(fn () => $this->app->make(BundleMerchantItems::class)()));
        // Voucher money held by orders nobody paid for goes back to the vouchers (MA-211).
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command(ReleaseVoucherHoldsCommand::class)->everyTenMinutes()->withoutOverlapping();
            // Gift choice cards due today and their reminders, from 8 in the morning (MA-235).
            $schedule->command(SendGiftChoicesCommand::class)->hourly()->withoutOverlapping();
        });
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(OrderPaid::class, SendVouchers::class);
        Event::listen(OrderPaid::class, SendGiftChoices::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ReleaseVoucherHoldsCommand::class, SendGiftChoicesCommand::class]);
        }
    }
}
