<?php

namespace App\Modules\Admin;

use App\Modules\Admin\Console\SetPanelUser;
use App\Modules\Shared\ModuleServiceProvider;

class AdminServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([SetPanelUser::class]);
        }
    }
}
