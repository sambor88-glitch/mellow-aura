<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Settings $settings): View
    {
        $live = Product::query()->live()->with(['category', 'variants', 'media'])->orderBy('sort_order')->get();

        return view('content::home', [
            'hero' => $live->firstWhere('slug', $settings->get('home_hero_product')) ?? $live->first(),
            'featured' => $live->take(4),
        ]);
    }
}
