<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * „Duplikuj” on the product card (MA-307): a hidden copy right after the original, to change its name and what differs.
 * It takes the texts, sizes with prices (also in other currencies), translations and photos with their descriptions
 * and crops. It leaves behind what belonged to the original piece: the promotion (price before a reduction), the pieces
 * on the shelf (counted sizes start at 0), the people waiting for a size, the price history and the 3D model.
 */
class DuplicateProduct
{
    public function __invoke(Product $product): Product
    {
        $product->load(['variants.translations', 'variants.prices', 'translations']);

        $copy = DB::transaction(function () use ($product) {
            // The copy stands right after the original; everyone behind moves one place down.
            Product::query()->where('sort_order', '>', $product->sort_order)->toBase()->increment('sort_order');

            $copy = $product->replicate(['slug', 'sort_order', 'is_published']);
            $copy->name = $product->name.' (kopia)';
            $copy->slug = $this->uniqueSlug($copy->name);
            $copy->sort_order = $product->sort_order + 1;
            $copy->is_published = false;
            $copy->save();

            foreach ($product->translations as $translation) {
                $copy->translations()->create([
                    ...$translation->only($translation->getFillable()),
                    'name' => $translation->name.' (copy)',
                    'slug' => $this->uniqueTranslationSlug($translation->name.' copy', $translation->locale),
                ]);
            }

            foreach ($product->variants as $variant) {
                $twin = $copy->variants()->create([
                    'label' => $variant->label,
                    'price_gross' => $variant->price_gross,
                    'compare_at_price' => null,
                    'stock' => $variant->stock === null ? null : 0,
                    'sent_by_post' => $variant->sent_by_post,
                    'voucher_seats' => $variant->voucher_seats,
                    'is_hidden' => $variant->is_hidden,
                ]);

                foreach ($variant->translations as $translation) {
                    $twin->translations()->create($translation->only($translation->getFillable()));
                }

                foreach ($variant->prices as $price) {
                    $twin->prices()->create(['currency' => $price->currency, 'amount_minor' => $price->amount_minor, 'compare_at_minor' => null]);
                }
            }

            return $copy;
        });

        // Photos are files; they are copied after the rows, and a size keeps pointing at its own photo.
        $photoOf = [];

        foreach ($product->getMedia('images') as $photo) {
            $photoOf[$photo->id] = $photo->copy($copy, 'images')->id;
        }

        foreach ($copy->variants()->get() as $index => $twin) {
            $original = $product->variants[$index]->media_id ?? null;

            if ($original !== null && isset($photoOf[$original])) {
                $twin->update(['media_id' => $photoOf[$original]]);
            }
        }

        return $copy;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produkt';
        $slug = $base;

        for ($suffix = 2; Product::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function uniqueTranslationSlug(string $name, string $locale): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;

        for ($suffix = 2; ProductTranslation::query()->where('locale', $locale)->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
