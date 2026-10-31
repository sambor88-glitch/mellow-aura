<?php

namespace App\Modules\Workshops;

use App\Modules\Admin\Support\Search\PanelSearch;
use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\SitemapPages;
use App\Modules\Workshops\Console\SendRemindersCommand;
use App\Modules\Workshops\Listeners\SendBookingEmails;
use App\Modules\Workshops\Mail\BookingCancelled;
use App\Modules\Workshops\Mail\BookingConfirmed;
use App\Modules\Workshops\Mail\BookingMoved;
use App\Modules\Workshops\Mail\BookingOverbooked;
use App\Modules\Workshops\Mail\BookingReminder;
use App\Modules\Workshops\Support\Search\WorkshopDateSearch;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * Workshops: the price list page, booking a date online with its mails, and in the panel the price list
 * and the calendar of dates.
 */
class WorkshopsServiceProvider extends ModuleServiceProvider
{
    protected array $customerMails = [
        BookingConfirmed::class,
        BookingReminder::class,
        BookingCancelled::class,
        BookingMoved::class,
        BookingOverbooked::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->routes('workshops.index'));
        $this->callAfterResolving(PanelSearch::class, fn (PanelSearch $search) => $search->add(new WorkshopDateSearch));
        // The reminder about a day before each workshop (MA-138).
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command(SendRemindersCommand::class)->hourly()->withoutOverlapping());
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(OrderPaid::class, SendBookingEmails::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SendRemindersCommand::class]);
        }
    }
}
