<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\DeliveryOffers;
use App\Modules\Shared\Support\MerchantItem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The shop's products for the Google Merchant Center feed: every size of a published product that Kasia shows
 * in Google, with its photos, its price and whether it is on the shelf. A voucher is sold like a product, so it
 * comes along. A product without a photo stays out, because Google shows nothing without one.
 */
class ProductMerchantItems
{
    public function __construct(private Settings $settings) {}

    /**
     * @return iterable<MerchantItem>
     */
    public function __invoke(): iterable
    {
        $products = Product::query()
            ->where('is_published', true)
            ->where('show_in_google', true)
            ->with(['category', 'variants', 'media'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            $images = $product->getMedia('images')->map(fn (Media $image) => $image->getAvailableUrl(['card']))->values();

            if ($images->isEmpty()) {
                continue;
            }

            foreach ($product->variants as $variant) {
                $variant->setRelation('product', $product);
                // The crossed-out price from the panel, as the product page shows it.
                $reduced = $variant->compare_at_price !== null && $variant->compare_at_price > $variant->price_gross;

                yield new MerchantItem(
                    id: 'v'.$variant->id,
                    title: $variant->label === '' ? $product->name : $product->name.' — '.$variant->label,
                    description: $product->description ?: ($product->seo_description ?: $product->name),
                    link: route('product.show', $product).'?wariant='.$variant->id,
                    image: $images->first(),
                    price: $reduced ? $variant->compare_at_price : $variant->price_gross,
                    available: $variant->isInStock(),
                    // A voucher and a mug with the customer's own text don't wait on the shelf, so no delivery time is promised.
                    delivery: DeliveryOffers::for($variant->price_gross, ! $variant->takesCustomText() && ! $product->isVoucher(), $this->settings),
                    additionalImages: $images->slice(1)->values()->all(),
                    salePrice: $reduced ? $variant->price_gross : null,
                    googleCategory: $product->google_category?->value,
                    productType: $product->category->group->label().' > '.$product->category->name,
                    returnable: ! $variant->takesCustomText(),
                );
            }
        }
    }
}
