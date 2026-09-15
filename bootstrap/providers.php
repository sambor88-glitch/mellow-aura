<?php

use App\Modules\Admin\AdminServiceProvider;
use App\Modules\Cart\CartServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Checkout\CheckoutServiceProvider;
use App\Modules\Content\ContentServiceProvider;
use App\Modules\Settings\SettingsServiceProvider;
use App\Modules\Shared\SharedServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    SettingsServiceProvider::class,
    AdminServiceProvider::class,
    CatalogServiceProvider::class,
    CartServiceProvider::class,
    CheckoutServiceProvider::class,
    ContentServiceProvider::class,
];
