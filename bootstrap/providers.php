<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Content\ContentServiceProvider;
use App\Modules\Settings\SettingsServiceProvider;
use App\Modules\Shared\SharedServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    SettingsServiceProvider::class,
    CatalogServiceProvider::class,
    ContentServiceProvider::class,
];
