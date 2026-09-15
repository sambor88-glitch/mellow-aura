<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Shared\Support\BreadcrumbStructuredData;
use App\Modules\Shared\Support\Money;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\SchemaOrg\ItemAvailability;
use Spatie\SchemaOrg\OfferItemCondition;
use Spatie\SchemaOrg\Schema;

/**
 * JSON-LD for a product page: a ProductGroup with every variant as a Product with its own Offer,
 * plus the breadcrumbs shown on the page.
 */
class ProductStructuredData
{
    public static function for(Product $product): string
    {
        $url = route('product.show', $product);
        $images = $product->getMedia('images')->map(fn (Media $image) => $image->getUrl())->all();
        $brand = Schema::brand()->name('MellowAura');

        $variants = $product->variants->map(function (ProductVariant $variant) use ($product, $url, $images, $brand) {
            $item = Schema::product()
                ->name($variant->label === '' ? $product->name : $product->name.' — '.$variant->label)
                ->sku($product->slug.'-'.$variant->id)
                ->brand($brand)
                ->offers(Schema::offer()
                    ->url($url.'?wariant='.$variant->id)
                    ->price(Money::decimal($variant->price_gross))
                    ->priceCurrency('PLN')
                    ->availability($variant->isInStock() ? ItemAvailability::InStock : ItemAvailability::OutOfStock)
                    ->itemCondition(OfferItemCondition::NewCondition));

            return $images ? $item->image($images[0]) : $item;
        });

        $group = Schema::productGroup()
            ->name($product->name)
            ->url($url)
            ->brand($brand)
            ->productGroupID($product->slug)
            ->hasVariant($variants->all());

        if ($product->description) {
            $group->description($product->description);
        }

        if ($images) {
            $group->image($images);
        }

        return $group->toScript().BreadcrumbStructuredData::for([
            ['Sklep', route('shop.index')],
            [$product->category->name, route('shop.category', $product->category)],
            [$product->name, $url],
        ]);
    }
}
