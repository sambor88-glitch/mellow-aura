<?php

namespace App\Modules\MugConfigurator\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MugConfigurator\Support\MugOptions;
use Illuminate\View\View;

/**
 * /kubek-z-napisem: the customer types a text, sees it on Kasia's photo of the mug, picks a size
 * and the inside glaze. Without sizes or glazes in the panel there is nothing to sell.
 */
class MugController extends Controller
{
    public function __invoke(MugOptions $options): View
    {
        abort_if($options->sizes()->isEmpty() || $options->glazes()->isEmpty(), 404);

        return view('mug-configurator::show', ['options' => $options]);
    }
}
