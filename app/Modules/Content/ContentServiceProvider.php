<?php

namespace App\Modules\Content;

use App\Modules\Content\Console\RefreshInstagramFeedCommand;
use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\SitemapPages;
use Illuminate\Console\Scheduling\Schedule;

class ContentServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->routes(
            'home', 'content.about', 'content.studio', 'content.scarf', 'content.imprint', 'custom-orders.index', 'content.b2b',
            'content.faq', 'content.contact', 'content.terms', 'content.privacy',
        ));

        // New Instagram posts reach the home page within the hour; the server's scheduler runs schedule:run.
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command(RefreshInstagramFeedCommand::class)->hourly()->withoutOverlapping());
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([RefreshInstagramFeedCommand::class]);
        }
    }
}
