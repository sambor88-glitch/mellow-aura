<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use Illuminate\View\View;

/**
 * /o-mnie: Kasia in her own words, the material tiles from the panel and a few things from the shop.
 */
class AboutController extends Controller
{
    public function __invoke(): View
    {
        return view('content::about', [
            'featured' => Product::query()->live()->with(['category', 'variants', 'media'])->orderBy('sort_order')->take(4)->get(),
        ]);
    }
}
