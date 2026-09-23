<?php

namespace App\Modules\Shared;

use App\Modules\Shared\Listeners\RedirectMailToOneInbox;
use App\Modules\Shared\Support\MerchantFeed;
use App\Modules\Shared\Support\SitemapPages;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;

class SharedServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SitemapPages::class);
        $this->app->singleton(MerchantFeed::class);
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(MessageSending::class, RedirectMailToOneInbox::class);
    }
}
