<?php

namespace App\Modules\Shared\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Every address no route knows ends here, inside the web middleware, so the missing page shows the customer's own cart
 * (MA-259). An address that exists for another method still answers 405, as the router does without this route.
 */
class MissingPageController
{
    public function __invoke(Request $request, Router $router): never
    {
        $allowed = collect($router->getRoutes()->getRoutes())
            ->reject(fn (Route $route) => $route->isFallback)
            ->filter(fn (Route $route) => $route->matches($request, includingMethod: false))
            ->flatMap(fn (Route $route) => $route->methods())
            ->unique()->values()->all();

        if ($allowed !== []) {
            throw new MethodNotAllowedHttpException($allowed);
        }

        abort(404);
    }
}
