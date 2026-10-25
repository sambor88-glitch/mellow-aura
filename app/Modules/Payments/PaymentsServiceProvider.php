<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Gateways\Przelewy24Gateway;
use App\Modules\Payments\Gateways\TestGateway;
use App\Modules\Payments\Support\Przelewy24Client;
use App\Modules\Shared\ModuleServiceProvider;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // With Przelewy24's keys in .env the shop takes real payments; without them it pays itself on a laptop
        // and on staging (TestGateway refuses to run in production). Nothing outside this module knows which.
        $this->app->bind(
            PaymentGateway::class,
            fn ($app) => $app->make(Przelewy24Client::configured() ? Przelewy24Gateway::class : TestGateway::class),
        );
    }
}
