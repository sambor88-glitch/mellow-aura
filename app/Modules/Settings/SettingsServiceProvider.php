<?php

namespace App\Modules\Settings;

use App\Modules\Admin\Support\Search\PanelSearch;
use App\Modules\Settings\Support\Search\SettingSearch;
use App\Modules\Shared\ModuleServiceProvider;

class SettingsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Settings::class);
        $this->callAfterResolving(PanelSearch::class, fn (PanelSearch $search) => $search->add(new SettingSearch));
    }
}
