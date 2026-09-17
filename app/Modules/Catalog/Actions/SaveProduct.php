<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves a product from the panel together with its sizes and photo descriptions. A changed price lands
 * in price_history (the variant model records it), a size missing from the form is removed, and a new
 * product goes to the top of the list. The address (slug) is set once and survives renaming.
 */
class SaveProduct
{
    /**
     * @param  array{name: string, category_id: int, description: ?string, care_note: ?string, food_contact: ?string, deviation: ?string, size_tolerance: ?string, safety_warnings: ?string, google_category: ?int, show_in_google: bool, is_published: bool, is_one_off: bool, is_exact_piece: bool, dimensions: array<string, string>, occasions: list<string>, recipients: list<string>, variants: list<array{id: ?int, label: string, price_gross: int, compare_at_price: ?int, stock: ?int, sent_by_post?: bool}>, photo_alts?: array<int, string>}  $data
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

            $existing = $product->variants()->get()->keyBy('id');
            $kept = [];

            foreach ($data['variants'] as $row) {
                $values = ['label' => $row['label'], 'price_gross' => $row['price_gross'], 'compare_at_price' => $row['compare_at_price'] ?? null, 'stock' => $row['stock'], 'sent_by_post' => $row['sent_by_post'] ?? false];
                $variant = $row['id'] === null ? null : $existing->get($row['id']);

                if ($variant !== null) {
                    $variant->update($values);
                    $kept[] = $variant->id;
                } else {
                    $kept[] = $product->variants()->create($values)->id;
                }
            }

            $product->variants()->whereKeyNot($kept)->delete();

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
