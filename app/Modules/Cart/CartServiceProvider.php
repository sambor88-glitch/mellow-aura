<?php

namespace App\Modules\Cart;

use App\Modules\Cart\Lines\ProductLine;
use App\Modules\Cart\Lines\ProductLines;
use App\Modules\Shared\ModuleServiceProvider;

class CartServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Cart::class);
        $this->app->singleton(LineTypes::class, fn ($app) => (new LineTypes($app))->register(ProductLine::TYPE, ProductLines::class));
    }
}
