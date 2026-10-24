<?php

namespace App\Modules\Catalog\Seo;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Settings;

/**
 * ProductGroup with one Product + Offer per variant (spec, section 3).
 * Availability follows stock; a sold-out one-off stays as OutOfStock.
 */
class ProductSchema
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(Product $product, string $description): array
    {
        $url = route('product.show', $product);
        $images = $product->photos()->map(fn ($media) => $media->getUrl())->values()->all();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'ProductGroup',
            '@id' => $url.'#product',
            'name' => $product->name,
            'description' => $description,
            'url' => $url,
            'image' => $images,
            'brand' => ['@type' => 'Brand', 'name' => 'MellowAura'],
            'category' => $product->category?->name,
            'productGroupID' => $product->slug,
            'variesBy' => $product->variants->count() > 1 ? ['https://schema.org/size'] : null,
            'hasVariant' => $product->variants
                ->map(fn (ProductVariant $variant) => $this->variant($product, $variant, $url, $images))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @param  array<int, string>  $images
     * @return array<string, mixed>
     */
    private function variant(Product $product, ProductVariant $variant, string $productUrl, array $images): array
    {
        $variantUrl = $productUrl.'?variant='.$variant->id;

        return array_filter([
            '@type' => 'Product',
            'sku' => $product->slug.'-'.$variant->id,
            'name' => $product->name.' — '.$variant->label,
            'size' => $product->variants->count() > 1 ? $variant->label : null,
            'url' => $variantUrl,
            'image' => $images,
            'brand' => ['@type' => 'Brand', 'name' => 'MellowAura'],
            'offers' => array_filter([
                '@type' => 'Offer',
                'url' => $variantUrl,
                'priceCurrency' => 'PLN',
                'price' => number_format($variant->price_gross / 100, 2, '.', ''),
                'availability' => $variant->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@id' => rtrim((string) config('app.url'), '/').'/#pracownia'],
                'shippingDetails' => $this->shipping(),
                'hasMerchantReturnPolicy' => $this->returns(),
            ]),
        ]);
    }

    /**
     * Cheapest paid delivery (Paczkomat) to Poland, from the panel.
     *
     * @return array<string, mixed>|null
     */
    private function shipping(): ?array
    {
        $price = $this->settings->get('shipping.parcel_locker_price');

        if ($price === null) {
            return null;
        }

        return [
            '@type' => 'OfferShippingDetails',
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'PL'],
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => number_format(((int) $price) / 100, 2, '.', ''),
                'currency' => 'PLN',
            ],
        ];
    }

    /**
     * 14 days to send back a shop item (consumer law); items made to order are excluded in the terms.
     *
     * @return array<string, mixed>
     */
    private function returns(): array
    {
        return [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'PL',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 14,
            'returnMethod' => 'https://schema.org/ReturnByMail',
        ];
    }
}
