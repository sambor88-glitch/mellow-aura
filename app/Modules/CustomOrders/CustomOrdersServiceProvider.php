<?php

namespace App\Modules\CustomOrders;

use App\Modules\CustomOrders\Console\EraseExpiredRequestsCommand;
use App\Modules\CustomOrders\Mail\BriefConfirmed;
use App\Modules\CustomOrders\Mail\PaymentLinkSent;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Custom orders: the page /zamowienia-indywidualne with the form for an idea and its photos, and the mails about it.
 * The ideas wait for Kasia in the panel, where she sends a link to pay for one (MA-144). The link's page pays through
 * Checkout and Payments, like the shop, and ConfirmPayment counts the money towards the idea.
 */
class CustomOrdersServiceProvider extends ModuleServiceProvider
{
    protected array $customerMails = [
        BriefConfirmed::class,
        PaymentLinkSent::class,
    ];

    public function register(): void
    {
        // Data and photos past the time the privacy policy keeps them go once a night, after the backup at 02:50 (MA-312).
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command(EraseExpiredRequestsCommand::class)->dailyAt('03:30')->withoutOverlapping());
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([EraseExpiredRequestsCommand::class]);
        }
    }
}
