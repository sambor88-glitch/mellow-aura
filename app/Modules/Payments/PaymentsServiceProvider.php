<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Gateways\TestGateway;
use App\Modules\Shared\ModuleServiceProvider;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Until the Przelewy24 gateway arrives (MA-167), the shop pays itself on staging and on a laptop.
        // Nothing outside this module changes when it does.
        $this->app->bind(PaymentGateway::class, TestGateway::class);
    }
}
