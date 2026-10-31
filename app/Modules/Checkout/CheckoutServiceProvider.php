<?php

namespace App\Modules\Checkout;

use App\Modules\Admin\Support\Search\PanelSearch;
use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Checkout\Events\OrderPlaced;
use App\Modules\Checkout\Events\OrderShipped;
use App\Modules\Checkout\Events\WithdrawalSubmitted;
use App\Modules\Checkout\Listeners\SendOrderEmails;
use App\Modules\Checkout\Listeners\SendOrderSummary;
use App\Modules\Checkout\Listeners\SendServerPurchase;
use App\Modules\Checkout\Listeners\SendShippingNotice;
use App\Modules\Checkout\Listeners\SendWithdrawalEmails;
use App\Modules\Checkout\Mail\OrderAwaitingPayment;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Mail\OrderUnavailable;
use App\Modules\Checkout\Mail\ParcelOnItsWay;
use App\Modules\Checkout\Mail\WithdrawalConfirmed;
use App\Modules\Checkout\Support\Search\OrderSearch;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class CheckoutServiceProvider extends ModuleServiceProvider
{
    protected array $customerMails = [
        OrderAwaitingPayment::class,
        OrderConfirmed::class,
        OrderUnavailable::class,
        ParcelOnItsWay::class,
        WithdrawalConfirmed::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(PanelSearch::class, fn (PanelSearch $search) => $search->add(new OrderSearch));
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(OrderPlaced::class, SendOrderSummary::class);
        Event::listen(OrderPaid::class, SendOrderEmails::class);
        Event::listen(OrderPaid::class, SendServerPurchase::class);
        Event::listen(OrderShipped::class, SendShippingNotice::class);
        Event::listen(WithdrawalSubmitted::class, SendWithdrawalEmails::class);
    }
}
