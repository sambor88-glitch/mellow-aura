<?php

namespace App\Modules\Checkout;

use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Checkout\Events\WithdrawalSubmitted;
use App\Modules\Checkout\Listeners\SendOrderEmails;
use App\Modules\Checkout\Listeners\SendWithdrawalEmails;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class CheckoutServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Event::listen(OrderPaid::class, SendOrderEmails::class);
        Event::listen(WithdrawalSubmitted::class, SendWithdrawalEmails::class);
    }
}
