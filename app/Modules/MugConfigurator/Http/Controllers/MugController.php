<?php

namespace App\Modules\MugConfigurator\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\MugConfigurator\Support\MugStructuredData;
use App\Modules\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /kubek-z-napisem: the customer types a text, sees it on Kasia's photo of the mug, picks a size
 * and the inside glaze. Without sizes or glazes in the panel there is nothing to sell.
 * ?rozmiar=maly opens it with that size chosen, as Google Shopping links to each size.
 */
class MugController extends Controller
{
    public function __invoke(Request $request, MugOptions $options, Settings $settings): View
    {
        abort_if($options->sizes()->isEmpty() || $options->glazes()->isEmpty(), 404);

        return view('mug-configurator::show', [
            'options' => $options,
            'startSize' => $options->startSize(is_string($key = $request->query('rozmiar')) ? $key : null),
            'structuredData' => MugStructuredData::for($options, $settings),
        ]);
    }
}
