<?php

namespace App\Modules\Shared;

use Illuminate\Support\Facades\Route;

/**
 * In-page calls to action (home buttons, shop shortcuts) show up only once
 * their route exists, so a page never sends a customer to a 404. The header
 * and footer keep the spec addresses on purpose.
 */
final class Navigation
{
    /**
     * URL of a named route when it exists, null otherwise.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function url(string $route, array $parameters = []): ?string
    {
        return Route::has($route) ? route($route, $parameters) : null;
    }
}
