<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Console\SyncEuroRateCommand;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\CatalogSitemap;
use App\Modules\Catalog\Support\ProductMerchantItems;
use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\BusinessStructuredData;
use App\Modules\Shared\Support\MerchantFeed;
use App\Modules\Shared\Support\SitemapPages;
use Illuminate\Console\Scheduling\Schedule;

class CatalogServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->add(new CatalogSitemap));
        $this->callAfterResolving(MerchantFeed::class, fn (MerchantFeed $feed) => $feed->add(fn () => $this->app->make(ProductMerchantItems::class)()));

        // Euro prices that follow the NBP rate move within the hour of a new rate.
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command(SyncEuroRateCommand::class)->hourly()->withoutOverlapping());
    }

    public function boot(): void
    {
        parent::boot();

        BusinessStructuredData::priceRangeUsing(ProductVariant::priceRange(...));

        if ($this->app->runningInConsole()) {
            $this->commands([SyncEuroRateCommand::class]);
        }
    }
}
