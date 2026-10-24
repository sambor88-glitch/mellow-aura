<?php

namespace App\Modules\Shared;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Base provider for every module in app/Modules/<Name>.
 *
 * Loads the module's routes, views and migrations by convention, so a module
 * provider only has to extend this class and be listed in bootstrap/providers.php.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->bootRoutes();
        $this->loadViewsFrom($this->modulePath('resources/views'), $this->viewNamespace());
        $this->loadMigrationsFrom($this->modulePath('Database/Migrations'));
    }

    /**
     * Points Laravel at App\Modules\<Name>\Database\Factories\<Model>Factory
     * for models in App\Modules\<Name>\Models. Registered once, by Shared.
     */
    public static function registerFactoryResolver(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            if (preg_match('/^App\\\\Modules\\\\(\w+)\\\\Models\\\\(.+)$/', $modelName, $matches)) {
                return "App\\Modules\\{$matches[1]}\\Database\\Factories\\{$matches[2]}Factory";
            }

            $model = Str::startsWith($modelName, 'App\\Models\\')
                ? Str::after($modelName, 'App\\Models\\')
                : Str::after($modelName, 'App\\');

            return 'Database\\Factories\\'.$model.'Factory';
        });
    }

    protected function bootRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        if (is_file($web = $this->modulePath('routes/web.php'))) {
            Route::middleware('web')->group($web);
        }

        if (is_file($admin = $this->modulePath('routes/admin.php'))) {
            Route::middleware(['web', 'auth'])
                ->prefix('panel')
                ->name('admin.')
                ->group($admin);
        }
    }

    protected function modulePath(string $path = ''): string
    {
        $directory = dirname((new ReflectionClass(static::class))->getFileName());

        return $path === '' ? $directory : $directory.'/'.$path;
    }

    protected function viewNamespace(): string
    {
        return Str::lower(basename($this->modulePath()));
    }
}
