<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\SaveCategoryTexts;
use App\Modules\Catalog\Http\Requests\Admin\SaveCategoryTextsRequest;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Categories in the panel: what Google shows for each shop category and the introduction above its products.
 */
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('catalog::admin.categories.index', [
            'categories' => Category::query()->with('translations')->withCount('products')->orderBy('sort_order')->get(),
        ]);
    }

    public function update(SaveCategoryTextsRequest $request, Category $category, SaveCategoryTexts $saveTexts): RedirectResponse
    {
        ['texts' => $texts, 'translations' => $translations] = $request->texts();
        $saveTexts($category, $texts, $translations);

        return to_route('admin.categories.index')
            ->withFragment('kategoria-'.$category->id)
            ->with('panel_status', 'Zapisane. Google zobaczy zmianę przy następnej wizycie.');
    }
}
