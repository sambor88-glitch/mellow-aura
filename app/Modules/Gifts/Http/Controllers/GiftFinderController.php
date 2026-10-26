<?php

namespace App\Modules\Gifts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Enums\Occasion;
use App\Modules\Catalog\Enums\Recipient;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Gifts\Support\BudgetRanges;
use App\Modules\Gifts\Support\VoucherValidity;
use App\Modules\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /prezenty: products on sale filtered by who a gift is for, the occasion and the budget. The filters are
 * query parameters and the canonical address stays bare, as the specification asks.
 */
class GiftFinderController extends Controller
{
    public function __invoke(Request $request, Settings $settings): View
    {
        $recipient = Recipient::tryFrom((string) $request->query('recipient', ''));
        $occasion = Occasion::tryFrom((string) $request->query('occasion', ''));
        $budgets = BudgetRanges::from($settings);
        $budget = $budgets->firstWhere('key', (string) $request->query('budget', ''));

        $ideas = Product::query()->live()->with(['category', 'variants', 'media'])->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (Product $product) => $recipient === null || in_array($recipient->value, $product->recipients ?? [], true))
            ->filter(fn (Product $product) => $occasion === null || in_array($occasion->value, $product->occasions ?? [], true))
            ->filter(fn (Product $product) => $budget === null || BudgetRanges::contains($budget, (int) $product->variants->min('price_gross')))
            ->values();

        $current = ['recipient' => $recipient?->value, 'occasion' => $occasion?->value, 'budget' => $budget['key'] ?? null];
        $url = fn (array $changes) => route('gifts.index').(($query = array_filter([...$current, ...$changes])) ? '?'.http_build_query($query) : '');
        $chips = fn (string $parameter, string $everything, array $options) => collect([null => $everything, ...$options])
            ->map(fn (string $label, string $value) => [
                'label' => $label,
                'url' => $url([$parameter => $value ?: null]),
                'active' => ($current[$parameter] ?? '') === $value,
            ])
            ->values();

        $voucherCategory = Category::query()
            ->where('group', CategoryGroup::Workshops)
            ->whereHas('products', fn ($products) => $products->live())
            ->orderBy('sort_order')
            ->first();

        return view('gifts::gifts.index', [
            'ideas' => $ideas,
            'filters' => [
                'Dla kogo' => $chips('recipient', 'Dla każdego', collect(Recipient::cases())->mapWithKeys(fn (Recipient $case) => [$case->value => $case->label()])->all()),
                'Okazja' => $chips('occasion', 'Wszystkie okazje', collect(Occasion::cases())->mapWithKeys(fn (Occasion $case) => [$case->value => $case->label()])->all()),
                'Budżet' => $chips('budget', 'Każdy budżet', $budgets->mapWithKeys(fn (array $range) => [$range['key'] => $range['label']])->all()),
            ],
            'resetUrl' => route('gifts.index'),
            'filtered' => array_filter($current) !== [],
            // The size a card's button adds: the cheapest one on the shelf that needs no text of its own.
            'addable' => $ideas->mapWithKeys(fn (Product $product) => [$product->id => $product->variants
                ->filter(fn (ProductVariant $variant) => $variant->isInStock() && ! ($product->stamp_enabled && $variant->stock === null))
                ->sortBy('price_gross')
                ->first()]),
            'voucherCategory' => $voucherCategory,
            'voucherValidity' => VoucherValidity::label((int) $settings->get('voucher_validity_months', 12)),
        ]);
    }
}
