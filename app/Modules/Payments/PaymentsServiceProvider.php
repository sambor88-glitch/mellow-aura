<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Gateways\Przelewy24Gateway;
use App\Modules\Payments\Gateways\StripeGateway;
use App\Modules\Payments\Gateways\TestGateway;
use App\Modules\Payments\Przelewy24\Account;
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
        // Przelewy24 goes first: Stripe stays only until its code is removed (MA-167).
        $this->app->bind(PaymentGateway::class, fn ($app) => match (true) {
            Account::configured() => $app->make(Przelewy24Gateway::class),
            StripeKeys::configured() => $app->make(StripeGateway::class),
            default => $app->make(TestGateway::class),
        });
    }
}
