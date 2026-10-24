<?php

namespace App\Modules\Shared;

use App\Modules\Settings\Settings;
use App\Modules\Shared\Seo\OrganizationSchema;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;

class SharedServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        static::registerFactoryResolver();
    }

    public function boot(): void
    {
        parent::boot();

        View::composer(
            ['shared::components.layout', 'shared::partials.header', 'shared::partials.footer'],
            fn (ViewInstance $view) => $view->with('settings', $this->app->make(Settings::class)),
        );

        View::composer(
            'shared::components.layout',
            fn (ViewInstance $view) => $view->with('organization', $this->app->make(OrganizationSchema::class)),
        );
    }
}
