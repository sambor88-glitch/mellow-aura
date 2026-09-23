<?php

namespace App\Modules\Localization;

use App\Modules\Localization\Http\Middleware\SetLocale;
use App\Modules\Localization\Routing\LocalizedUrlGenerator;
use App\Modules\Localization\Routing\RouteTwins;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Contracts\Http\Kernel;

/**
 * The English version of the site. Pages are written once; this module gives the ones listed in
 * „localization.paths” a twin under /en/ with the same controller, and makes route() on an English page
 * lead to English addresses.
 */
class LocalizationServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Same wiring as the framework's own generator; the framework's extend() on „url” (session, signed-URL key)
        // stays attached to the binding and still runs.
        $this->app->singleton('url', function ($app) {
            $routes = $app['router']->getRoutes();
            $app->instance('routes', $routes);

            return new LocalizedUrlGenerator(
                $routes,
                $app->rebinding('request', fn ($app, $request) => $app['url']->setRequest($request)),
                $app['config']['app.asset_url'],
            );
        });
    }

    public function boot(): void
    {
        parent::boot();

        // Through the kernel: it overwrites the router's groups with its own list when it starts, so a middleware
        // pushed straight onto the router would be gone. Every web page gets it, so a Polish page after an English
        // one (the same process under Octane or in tests) is Polish again.
        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup('web', SetLocale::class);
        // Before route bindings: /en/product/{slug} looks the product up by its English slug.
        $kernel->prependToMiddlewarePriority(SetLocale::class);

        // After every module has added its routes, so each twin copies a finished route. A cached route list
        // already holds the twins.
        $this->app->booted(function () {
            if (! $this->app->routesAreCached()) {
                RouteTwins::register($this->app['router']);
            }
        });
    }
}
