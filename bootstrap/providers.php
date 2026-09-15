<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Settings\SettingsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CatalogServiceProvider::class,
    SettingsServiceProvider::class,
];
