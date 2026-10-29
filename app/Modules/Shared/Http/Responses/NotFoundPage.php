<?php

namespace App\Modules\Shared\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * The missing page in the site's own layout, with the menu, the cart and the footer (MA-259). The layout reads the
 * database and the settings; when that fails, null hands the error back to Laravel, which shows the standalone
 * resources/views/errors/404.blade.php — the customer still gets a page, not a second error.
 */
class NotFoundPage
{
    public static function render(Request $request): ?Response
    {
        if ($request->expectsJson()) {
            return null;
        }

        try {
            return response()->view('shared::errors.not-found', [], 404);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
