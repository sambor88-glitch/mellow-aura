<?php

namespace App\Modules\Shipping;

use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shipping\Console\SyncShipmentsCommand;
use Illuminate\Console\Scheduling\Schedule;

class ShippingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // ShipX statuses move the orders along within a quarter of an hour (MA-133).
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command(SyncShipmentsCommand::class)->everyFifteenMinutes()->withoutOverlapping());
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncShipmentsCommand::class]);
        }
    }
}
