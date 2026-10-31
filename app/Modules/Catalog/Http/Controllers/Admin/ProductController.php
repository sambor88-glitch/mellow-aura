<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\AddProductPhotos;
use App\Modules\Catalog\Actions\ConvertEuroPrices;
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
 * Products in the panel (MA-307): a list to work on, a short form for a new product, the card of one product in tabs,
 * and the settings that apply to all of them — the home page hero and the markup on euro prices that follow the NBP rate.
 */
class ProductController extends Controller
{
    /** The tabs of the product card, in their order. */
    public const TABS = ['sale', 'desc', 'details', 'gift', 'en'];

    public function index(): View
    {
        return view('catalog::admin.products.index', [
            'products' => Product::query()
                // Translations for the English marker on the list; the people waiting for a sold-out size help Kasia
                // decide what to make next (MA-266).
                ->with(['category', 'variants' => fn ($query) => $query->orderBy('id')->withCount('stockNotifications'), 'variants.prices', 'media', 'translations'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('catalog::admin.products.create', [
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'photoLimits' => PhotoRules::limits(),
        ]);
    }

    public function edit(Request $request, Product $product): View
    {
        $product->load(['category', 'variants' => fn ($query) => $query->orderBy('id')->withCount('stockNotifications'), 'variants.translations', 'variants.prices', 'media', 'translations']);
        // The card walks to the product before and after it, in the order of the shop.
        $order = Product::query()->orderBy('sort_order')->orderBy('id')->pluck('id');
        $at = $order->search($product->id);

        return view('catalog::admin.products.edit', [
            'product' => $product,
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'photoLimits' => PhotoRules::limits(),
            'tab' => in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'sale',
            'position' => $at + 1,
            'total' => $order->count(),
            'previous' => $at > 0 ? $order[$at - 1] : null,
            'next' => $order[$at + 1] ?? null,
        ]);
    }

    public function settings(Settings $settings): View
    {
        return view('catalog::admin.products.settings', [
            'products' => Product::query()->where('is_published', true)->orderBy('sort_order')->orderBy('id')->get(),
            'heroSlug' => $settings->get('home_hero_product'),
            'heroBadge' => $settings->get('home_hero_badge'),
            'euroMarkup' => $settings->get(ConvertEuroPrices::MARKUP),
        ]);
    }

    /** A new product opens on its description: that is what the short form leaves for later. */
    public function store(SaveProductRequest $request, SaveProduct $saveProduct, AddProductPhotos $addPhotos): RedirectResponse
    {
        $product = $saveProduct(null, $request->product());

        if ($request->hasFile('photos')) {
            $addPhotos($product, $request->file('photos'));
        }

        return to_route('admin.products.edit', [$product, 'tab' => 'desc'])
            ->with('panel_status', $product->name.' — '.($product->is_published ? 'opublikowane w sklepie. Dopisz jeszcze opis.' : 'zapisane jako ukryte'));
    }

    /** „Zapisz i następny” saves and opens the next product on the same tab. */
    public function update(SaveProductRequest $request, Product $product, SaveProduct $saveProduct): RedirectResponse
    {
        $saveProduct($product, $request->product());
        $tab = in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'sale';
        $next = $request->filled('next') ? Product::query()->find($request->integer('next')) : null;

        return to_route('admin.products.edit', [$next ?? $product, 'tab' => $tab])
            ->with('panel_status', $next ? 'Zapisane. Teraz: '.$next->name.'.' : 'Zapisane. Klienci już to widzą.');
    }

    public function move(Request $request, Product $product, MoveProduct $moveProduct): RedirectResponse
    {
        $moveProduct($product, $request->input('kierunek') === 'wyzej' ? -1 : 1);

        return to_route('admin.products.index', ['kolejnosc' => 1])->withFragment('produkt-'.$product->id);
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

        return to_route('admin.products.settings')->with('panel_status', 'Zapisane. Strona główna już to pokazuje.');
    }

    /**
     * The markup on euro prices counted from the NBP rate. Saving it recounts them at once, so the English shop
     * does not wait for the next hourly rate.
     */
    public function euroMarkup(Request $request, SaveSettings $saveSettings, ConvertEuroPrices $convertEuroPrices): RedirectResponse
    {
        $data = $request->validate([
            ConvertEuroPrices::MARKUP => ['nullable', 'integer', 'min:0', 'max:100'],
        ], [
            ConvertEuroPrices::MARKUP.'.integer' => 'Wpisz pełną liczbę procent, np. 15',
            ConvertEuroPrices::MARKUP.'.min' => 'Wpisz pełną liczbę procent, np. 15',
            ConvertEuroPrices::MARKUP.'.max' => 'Narzut może wynieść najwyżej :max%',
        ]);

        $saveSettings([ConvertEuroPrices::MARKUP => isset($data[ConvertEuroPrices::MARKUP]) ? (int) $data[ConvertEuroPrices::MARKUP] : null]);
        $convertEuroPrices();

        return to_route('admin.products.settings')
            ->withFragment('ceny-w-euro')
            ->with('panel_status', 'Zapisane. Ceny w euro z kursu NBP już liczą się z tym narzutem.');
    }
}
