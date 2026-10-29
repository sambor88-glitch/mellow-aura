<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\SaveCategoryTexts;
use App\Modules\Catalog\Http\Requests\Admin\SaveCategoryTextsRequest;
use App\Modules\Catalog\Http\Requests\Admin\SaveShopShortcutsRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\ShopShortcuts;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Categories in the panel: what Google shows for each shop category and the introduction above its products,
 * plus the line under each shortcut card under the shop's products.
 */
class CategoryController extends Controller
{
    public function index(Settings $settings): View
    {
        return view('catalog::admin.categories.index', [
            'categories' => Category::query()->with('translations')->withCount('products')->orderBy('sort_order')->get(),
            'shortcutNotes' => collect(ShopShortcuts::ROUTES)->keys()->mapWithKeys(fn (string $label) => [$label => [
                'pl' => $settings->get(ShopShortcuts::noteKey($label, 'pl')),
                'en' => $settings->get(ShopShortcuts::noteKey($label, 'en')),
            ]])->all(),
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

    public function shortcuts(SaveShopShortcutsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.categories.index')
            ->withFragment('skroty')
            ->with('panel_status', 'Podpisy zapisane. Sklep już je pokazuje.');
    }
}
