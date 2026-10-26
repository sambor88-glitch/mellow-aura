<?php

namespace App\Modules\Localization\Routing;

use App\Modules\Localization\Support\Locales;
use Illuminate\Routing\Router;

/**
 * Gives every page listed in „localization.paths” a twin in each switched-on language: the same controller,
 * middleware and constraints under the language's prefix, named „<locale>.<name>”, with „locale” in its action.
 */
class RouteTwins
{
    public static function register(Router $router): void
    {
        $routes = $router->getRoutes();

        // ->name() runs after a route is added, so the name index is stale until refreshed.
        $routes->refreshNameLookups();

        foreach (Locales::enabled() as $locale) {
            if ($locale === Locales::default()) {
                continue;
            }

            $prefix = config("localization.locales.$locale.prefix");

            foreach (config("localization.paths.$locale", []) as $name => $path) {
                $original = $routes->getByName($name);

                // Listed but not built yet (Journal comes after the holidays).
                if ($original === null) {
                    continue;
                }

                $action = $original->getAction();
                unset($action['as'], $action['prefix']);

                $router->match($original->methods(), trim($prefix.'/'.ltrim($path, '/'), '/'), [
                    ...$action,
                    'as' => "$locale.$name",
                    'locale' => $locale,
                ])->setWheres($original->wheres);
            }
        }

        $routes->refreshNameLookups();
        $routes->refreshActionLookups();
    }
}
