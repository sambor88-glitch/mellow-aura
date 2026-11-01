<?php

namespace App\Modules\Gifts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\GiftTags;
use App\Modules\Gifts\Support\BudgetRanges;
use App\Modules\Gifts\Support\VoucherValidity;
use App\Modules\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /prezenty: products on sale filtered by who a gift is for, the occasion and the budget — all three lists from the
 * panel (MA-352); a person or an occasion without a product on sale has no chip. The filters are query parameters and
 * the canonical address stays bare, as the specification asks.
 */
class GiftFinderController extends Controller
{
    public function __invoke(Request $request, Settings $settings): View
    {
        $recipients = GiftTags::recipients($settings);
        $occasions = GiftTags::occasions($settings);
        $recipient = array_key_exists($typed = (string) $request->query('recipient', ''), $recipients) ? $typed : null;
        $occasion = array_key_exists($typed = (string) $request->query('occasion', ''), $occasions) ? $typed : null;
        $budgets = BudgetRanges::from($settings);
        $budget = $budgets->firstWhere('key', (string) $request->query('budget', ''));

        $onSale = Product::query()->live()->with(['category', 'variants' => fn ($variants) => $variants->visible(), 'media'])->orderBy('sort_order')->orderBy('id')->get();
        $ideas = $onSale
            ->filter(fn (Product $product) => $recipient === null || in_array($recipient, $product->recipients ?? [], true))
            ->filter(fn (Product $product) => $occasion === null || in_array($occasion, $product->occasions ?? [], true))
            ->filter(fn (Product $product) => $budget === null || BudgetRanges::contains($budget, (int) $product->variants->min('price_gross')))
            ->values();

        // A chip no product on sale is ticked with would lead to an empty list, so it stays off the page — unless the
        // address already chose it (MA-352).
        $withProducts = fn (array $tags, string $field, ?string $chosen) => array_filter(
            $tags,
            fn (string $key) => $key === $chosen || $onSale->contains(fn (Product $product) => in_array($key, (array) $product->{$field}, true)),
            ARRAY_FILTER_USE_KEY,
        );
        $recipients = $withProducts($recipients, 'recipients', $recipient);
        $occasions = $withProducts($occasions, 'occasions', $occasion);

        $current = ['recipient' => $recipient, 'occasion' => $occasion, 'budget' => $budget['key'] ?? null];
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
                'Dla kogo' => $chips('recipient', 'Dla każdego', $recipients),
                'Okazja' => $chips('occasion', 'Wszystkie okazje', $occasions),
                'Budżet' => $chips('budget', 'Każdy budżet', $budgets->mapWithKeys(fn (array $range) => [$range['key'] => $range['label']])->all()),
            ],
            'resetUrl' => route('gifts.index'),
            'filtered' => array_filter($current) !== [],
            'voucherCategory' => $voucherCategory,
            'voucherValidity' => VoucherValidity::label((int) $settings->get('voucher_validity_months', 12)),
        ]);
    }
}
