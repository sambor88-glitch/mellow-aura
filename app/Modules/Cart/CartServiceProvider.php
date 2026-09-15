<?php

namespace App\Modules\Cart;

use App\Modules\Shared\ModuleServiceProvider;

class CartServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Cart::class);
    }
}
