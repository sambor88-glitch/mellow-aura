<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Support\Search\PanelSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The results for the search at the top of the panel (MA-308), as JSON for resources/js/panel-search.js.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, PanelSearch $search): JsonResponse
    {
        $term = Str::limit(trim((string) $request->query('q')), 80, '');

        return response()->json(['groups' => $search->run($request->user(), $term)]);
    }
}
