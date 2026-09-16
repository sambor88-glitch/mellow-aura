<?php

use App\Modules\Admin\AdminServiceProvider;
use App\Modules\Cart\CartServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Checkout\CheckoutServiceProvider;
use App\Modules\Consent\ConsentServiceProvider;
use App\Modules\Content\ContentServiceProvider;
use App\Modules\Gifts\GiftsServiceProvider;
use App\Modules\MugConfigurator\MugConfiguratorServiceProvider;
use App\Modules\Settings\SettingsServiceProvider;
use App\Modules\Shared\SharedServiceProvider;
use App\Modules\Workshops\WorkshopsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    SettingsServiceProvider::class,
    AdminServiceProvider::class,
    CatalogServiceProvider::class,
    CartServiceProvider::class,
    CheckoutServiceProvider::class,
    GiftsServiceProvider::class,
    MugConfiguratorServiceProvider::class,
    ContentServiceProvider::class,
    ConsentServiceProvider::class,
    WorkshopsServiceProvider::class,
];
