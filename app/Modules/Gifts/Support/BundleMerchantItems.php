<?php

namespace App\Modules\Gifts\Support;

use App\Modules\Gifts\Models\Bundle;
use App\Modules\Gifts\Models\BundleItem;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\DeliveryOffers;
use App\Modules\Shared\Support\MerchantItem;

/**
 * The gift sets on sale now for the Google Merchant Center feed, each as one bundle at the set's price,
 * linked to its place on /zestawy-prezentowe.
 */
class BundleMerchantItems
{
    public function __construct(private Settings $settings) {}

    /**
     * @return iterable<MerchantItem>
     */
    public function __invoke(): iterable
    {
        $bundles = Bundle::query()->live()->with('items.variant.product.media')->orderBy('sort_order')->orderBy('id')->get();

        foreach ($bundles as $bundle) {
            $images = $bundle->items
                ->map(fn (BundleItem $item) => $item->variant->product->getFirstMedia('images')?->getAvailableUrl(['card']))
                ->filter()
                ->unique()
                ->values();

            yield new MerchantItem(
                id: 'bundle-'.$bundle->id,
                title: $bundle->name,
                description: $bundle->description ?: 'Zestaw: '.$bundle->items->map(fn (BundleItem $item) => $item->variant->product->name)->join(', '),
                link: route('bundles.index').'#zestaw-'.$bundle->id,
                image: $images->first(),
                price: $bundle->price(),
                // A set is on sale only while every part is.
                available: true,
                delivery: DeliveryOffers::for($bundle->price(), true, $this->settings),
                additionalImages: $images->slice(1)->values()->all(),
                productType: 'Zestawy prezentowe',
                isBundle: true,
            );
        }
    }
}
