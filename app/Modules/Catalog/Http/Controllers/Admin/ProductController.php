<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\AddProductPhotos;
use App\Modules\Catalog\Actions\MoveProduct;
use App\Modules\Catalog\Actions\SaveProduct;
use App\Modules\Catalog\Http\Requests\Admin\PhotoRules;
use App\Modules\Catalog\Http\Requests\Admin\SaveProductRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Products in the panel, like the prototype's „Produkty” tab: a form to add one, the home page hero,
 * and the whole offer with order, visibility, photos and an edit form under each product.
 */
class ProductController extends Controller
{
    public function index(Settings $settings): View
    {
        return view('catalog::admin.products.index', [
            'products' => Product::query()
                ->with(['category', 'variants' => fn ($query) => $query->orderBy('id'), 'media'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'heroSlug' => $settings->get('home_hero_product'),
            'heroBadge' => $settings->get('home_hero_badge'),
            'photoLimits' => PhotoRules::limits(),
        ]);
    }

    public function store(SaveProductRequest $request, SaveProduct $saveProduct, AddProductPhotos $addPhotos): RedirectResponse
    {
        $product = $saveProduct(null, $request->product());

        if ($request->hasFile('photos')) {
            $addPhotos($product, $request->file('photos'));
        }

        return to_route('admin.products.index')
            ->with('panel_status', $product->name.' — '.($product->is_published ? 'opublikowane w sklepie' : 'zapisane jako ukryte'));
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $saveProduct): RedirectResponse
    {
        $saveProduct($product, $request->product());

        return to_route('admin.products.index')
            ->withFragment('produkt-'.$product->id)
            ->with('panel_status', 'Zapisane. Klienci już to widzą.');
    }

    public function move(Request $request, Product $product, MoveProduct $moveProduct): RedirectResponse
    {
        $moveProduct($product, $request->input('kierunek') === 'wyzej' ? -1 : 1);

        return to_route('admin.products.index')->withFragment('produkt-'.$product->id);
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_published' => ! $product->is_published]);

        return to_route('admin.products.index')
            ->withFragment('produkt-'.$product->id)
            ->with('panel_status', $product->name.' — '.($product->is_published ? 'widać w sklepie' : 'ukryte w sklepie'));
    }

    public function hero(Request $request, SaveSettings $saveSettings): RedirectResponse
    {
        $data = $request->validate([
            'home_hero_product' => ['required', Rule::exists('products', 'slug')],
            'home_hero_badge' => ['nullable', 'string', 'max:30'],
        ], [
            'home_hero_product.required' => 'Wybierz produkt na duże zdjęcie',
            'home_hero_product.exists' => 'Wybierz produkt na duże zdjęcie',
            'home_hero_badge.max' => 'Etykietę zmieszczę do :max znaków',
        ]);

        $saveSettings([
            'home_hero_product' => $data['home_hero_product'],
            'home_hero_badge' => $data['home_hero_badge'] ?? null,
        ]);

        return to_route('admin.products.index')->with('panel_status', 'Zapisane. Strona główna już to pokazuje.');
    }
}
