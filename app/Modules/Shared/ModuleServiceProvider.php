<?php

namespace App\Modules\Shared;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Base provider for app/Modules/<Name>. A module provider only extends this class
 * and is registered in bootstrap/providers.php.
 *
 * Loads, when present: routes/web.php, routes/admin.php (prefix /panel, name admin.*, signed-in accounts allowed there),
 * resources/views (namespace = snake-case module name), resources/views/components as
 * anonymous Blade components (<x-module::name>), lang (translations as __('module::file.key')), Database/Migrations,
 * and points model factories at Database/Factories.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $dir = dirname((new ReflectionClass(static::class))->getFileName());
        $name = class_basename((new ReflectionClass(static::class))->getNamespaceName());
        $alias = Str::snake($name, '-');

        if (is_file($dir.'/routes/web.php')) {
            Route::middleware('web')->group($dir.'/routes/web.php');
        }

        if (is_file($dir.'/routes/admin.php')) {
            // „panel-route” (Admin module) keeps a helper's account to the screens it may open.
            Route::middleware(['web', 'auth', 'can:panel-route'])
                ->prefix('panel')
                ->name('admin.')
                ->group($dir.'/routes/admin.php');
        }

        if (is_dir($dir.'/resources/views')) {
            $this->loadViewsFrom($dir.'/resources/views', $alias);
        }

        if (is_dir($dir.'/resources/views/components')) {
            Blade::anonymousComponentPath($dir.'/resources/views/components', $alias);
        }

        if (is_dir($dir.'/lang')) {
            $this->loadTranslationsFrom($dir.'/lang', $alias);
        }

        if (is_dir($dir.'/Database/Migrations')) {
            $this->loadMigrationsFrom($dir.'/Database/Migrations');
        }

        Factory::guessFactoryNamesUsing(function (string $model): string {
            if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)\\\\Models\\\\(.+)$/', $model, $m)) {
                return "App\\Modules\\{$m[1]}\\Database\\Factories\\{$m[2]}Factory";
            }

            return 'Database\\Factories\\'.class_basename($model).'Factory';
        });
    }
}
