<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductTranslation;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves a product from the panel together with its sizes, the photo of each size and photo descriptions. A changed price lands
 * in price_history (the variant model records it), a size missing from the form is removed, and a new
 * product goes to the top of the list. The address (slug) is set once and survives renaming.
 *
 * Prices in other currencies ride along the same way, each with its own history.
 *
 * Its other languages ride along: a product with an English name gets an English row (and its own English
 * address, also set once); an emptied English name removes the row, and the product leaves /en/.
 */
class SaveProduct
{
    /**
     * @param  array{name: string, category_id: int, description: ?string, care_note: ?string, food_contact: ?string, deviation: ?string, size_tolerance: ?string, safety_warnings: ?string, google_category: ?int, show_in_google: bool, is_published: bool, is_one_off: bool, is_exact_piece: bool, dimensions: array<string, string>, occasions: list<string>, recipients: list<string>, variants: list<array{id: ?int, label: string, labels?: array<string, string>, price_gross: int, compare_at_price: ?int, prices?: array<string, array{amount: ?int, compare_at: ?int}>, stock: ?int, sent_by_post?: bool, media_id?: ?int}>, photo_alts?: array<int, string>, translations?: array<string, array<string, ?string>>}  $data
     */
    public function __invoke(?Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $attributes = [
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'description' => $data['description'],
                'care_note' => $data['care_note'],
                'food_contact' => $data['food_contact'],
                'deviation' => $data['deviation'],
                'size_tolerance' => $data['size_tolerance'],
                'safety_warnings' => $data['safety_warnings'],
                'google_category' => $data['google_category'],
                'show_in_google' => $data['show_in_google'],
                'is_published' => $data['is_published'],
                'is_one_off' => $data['is_one_off'],
                'is_exact_piece' => $data['is_exact_piece'],
                'dimensions' => $data['dimensions'] ?: null,
                'occasions' => $data['occasions'],
                'recipients' => $data['recipients'],
            ];

            if ($product === null) {
                // Everyone moves one place down, because the column takes no negative numbers.
                // toBase() leaves updated_at alone: the other products did not change.
                Product::query()->toBase()->increment('sort_order');

                $product = Product::create([
                    ...$attributes,
                    'slug' => $this->uniqueSlug($data['name']),
                    'sort_order' => 0,
                ]);
            } else {
                $product->update($attributes);
            }

            // A size may point only at a photo of this product; anything else falls back to the first photo.
            $photoIds = $product->getMedia('images')->modelKeys();
            $existing = $product->variants()->get()->keyBy('id');
            $kept = [];

            foreach ($data['variants'] as $row) {
                $values = ['label' => $row['label'], 'price_gross' => $row['price_gross'], 'compare_at_price' => $row['compare_at_price'] ?? null, 'stock' => $row['stock'], 'sent_by_post' => $row['sent_by_post'] ?? false, 'media_id' => in_array($row['media_id'] ?? null, $photoIds, true) ? $row['media_id'] : null];
                $variant = $row['id'] === null ? null : $existing->get($row['id']);

                if ($variant !== null) {
                    $variant->update($values);
                } else {
                    $variant = $product->variants()->create($values);
                }

                $kept[] = $variant->id;
                $this->saveVariantLabels($variant, $row['labels'] ?? []);
                $this->savePrices($variant, $row['prices'] ?? []);
            }

            $product->variants()->whereKeyNot($kept)->delete();

            foreach ($data['translations'] ?? [] as $locale => $fields) {
                $this->saveTranslation($product, $locale, $fields);
            }

            $alts = $data['photo_alts'] ?? [];

            foreach ($alts ? $product->getMedia('images') : [] as $photo) {
                if (! array_key_exists($photo->id, $alts)) {
                    continue;
                }

                // Without a description the page describes the photo with the product name.
                $alts[$photo->id] === ''
                    ? $photo->forgetCustomProperty('alt')
                    : $photo->setCustomProperty('alt', $alts[$photo->id]);

                $photo->save();
            }

            return $product;
        });
    }

    /**
     * @param  array<string, ?string>  $fields
     */
    private function saveTranslation(Product $product, string $locale, array $fields): void
    {
        $existing = $product->translations()->where('locale', $locale)->first();

        if (blank($fields['name'] ?? null)) {
            $existing?->delete();

            return;
        }

        $product->translations()->updateOrCreate(['locale' => $locale], [
            ...$fields,
            'slug' => $existing?->slug ?? $this->uniqueTranslationSlug($fields['name'], $locale),
        ]);
    }

    /**
     * @param  array<string, string>  $labels  locale => label; an empty label removes that language's row
     */
    private function saveVariantLabels(ProductVariant $variant, array $labels): void
    {
        foreach ($labels as $locale => $label) {
            $label === ''
                ? $variant->translations()->where('locale', $locale)->delete()
                : $variant->translations()->updateOrCreate(['locale' => $locale], ['label' => $label]);
        }
    }

    /**
     * Prices in other currencies. A changed amount lands in price_history under its currency (the Price model
     * records it); an emptied one removes the row, and the size leaves the shop in that currency.
     *
     * @param  array<string, array{amount: ?int, compare_at: ?int}>  $prices  currency => amounts in its smallest unit
     */
    private function savePrices(ProductVariant $variant, array $prices): void
    {
        foreach ($prices as $currency => $price) {
            if ($price['amount'] === null) {
                $variant->prices()->where('currency', $currency)->delete();

                continue;
            }

            $row = $variant->prices()->firstOrNew(['currency' => $currency]);
            $row->fill(['amount_minor' => $price['amount'], 'compare_at_minor' => $price['compare_at']])->save();
        }
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

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produkt';
        $slug = $base;

        for ($suffix = 2; Product::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
