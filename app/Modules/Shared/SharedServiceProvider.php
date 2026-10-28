<?php

namespace App\Modules\Shared;

use App\Modules\Shared\Console\LaunchCheckCommand;
use App\Modules\Shared\Listeners\RedirectMailToOneInbox;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Support\MerchantFeed;
use App\Modules\Shared\Support\SitemapPages;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;

class SharedServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SitemapPages::class);
        $this->app->singleton(MerchantFeed::class);
        $this->app->scoped(MailTexts::class);
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(MessageSending::class, RedirectMailToOneInbox::class);

        // Every mail template reads the sentences Kasia wrote in the panel through $mailTexts.
        View::composer('*::mail.*', fn ($view) => $view->with('mailTexts', $this->app->make(MailTexts::class)));

        if ($this->app->runningInConsole()) {
            $this->commands([LaunchCheckCommand::class]);
        }
    }
}
