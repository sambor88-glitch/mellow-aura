<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves a product from the panel together with its sizes. A changed price lands in price_history
 * (the variant model records it), a size missing from the form is removed, and a new product
 * goes to the top of the list. The address (slug) is set once and survives renaming.
 */
class SaveProduct
{
    /**
     * @param  array{name: string, category_id: int, description: ?string, care_note: ?string, is_published: bool, is_one_off: bool, dimensions: array<string, string>, occasions: list<string>, recipients: list<string>, variants: list<array{id: ?int, label: string, price_gross: int, stock: ?int}>}  $data
     */
    public function __invoke(?Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $attributes = [
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'description' => $data['description'],
                'care_note' => $data['care_note'],
                'is_published' => $data['is_published'],
                'is_one_off' => $data['is_one_off'],
                'dimensions' => $data['dimensions'] ?: null,
                'occasions' => $data['occasions'],
                'recipients' => $data['recipients'],
            ];

            if ($product === null) {
                $product = Product::create([
                    ...$attributes,
                    'slug' => $this->uniqueSlug($data['name']),
                    'sort_order' => (int) Product::query()->min('sort_order') - 1,
                ]);
            } else {
                $product->update($attributes);
            }

            $existing = $product->variants()->get()->keyBy('id');
            $kept = [];

            foreach ($data['variants'] as $row) {
                $values = ['label' => $row['label'], 'price_gross' => $row['price_gross'], 'stock' => $row['stock']];
                $variant = $row['id'] === null ? null : $existing->get($row['id']);

                if ($variant !== null) {
                    $variant->update($values);
                    $kept[] = $variant->id;
                } else {
                    $kept[] = $product->variants()->create($values)->id;
                }
            }

            $product->variants()->whereKeyNot($kept)->delete();

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
