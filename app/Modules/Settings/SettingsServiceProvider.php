<?php

namespace App\Modules\Settings;

use App\Modules\Shared\ModuleServiceProvider;

class SettingsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Settings::class);
    }
}
