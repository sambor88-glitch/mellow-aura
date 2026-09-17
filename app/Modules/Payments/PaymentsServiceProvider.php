<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Gateways\StripeGateway;
use App\Modules\Payments\Gateways\TestGateway;
use App\Modules\Payments\Support\StripeKeys;
use App\Modules\Shared\ModuleServiceProvider;
use Stripe\StripeClient;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, fn () => new StripeClient((string) StripeKeys::secret()));

        // The keys decide who takes the payment. Until they are in the env, the shop keeps paying
        // itself on staging and on a laptop, and nothing outside this module changes when they arrive.
        $this->app->bind(PaymentGateway::class, fn ($app) => StripeKeys::configured()
            ? $app->make(StripeGateway::class)
            : $app->make(TestGateway::class));
    }
}
