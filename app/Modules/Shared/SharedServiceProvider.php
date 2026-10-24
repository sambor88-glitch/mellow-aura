<?php

namespace App\Modules\Shared;

use App\Modules\Shared\Console\NotifyStagingReview;

class SharedServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        static::registerFactoryResolver();
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([NotifyStagingReview::class]);
        }
    }
}
