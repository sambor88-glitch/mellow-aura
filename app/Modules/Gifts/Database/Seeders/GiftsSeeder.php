<?php

namespace App\Modules\Gifts\Database\Seeders;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Gifts\Models\Bundle;
use Illuminate\Database\Seeder;

/**
 * The three gift sets from the prototype (bundles.json), made of the sizes their descriptions name.
 * It only adds a set that is missing, so changes made in the panel stay. Run it after CatalogSeeder.
 */
class GiftsSeeder extends Seeder
{
    public function run(): void
    {
        $bundles = json_decode(file_get_contents(__DIR__.'/bundles.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($bundles as $row) {
            if (Bundle::query()->where('name', $row['name'])->exists()) {
                continue;
            }

            $bundle = Bundle::create(collect($row)->except('parts')->all());

            foreach ($row['parts'] as $index => $part) {
                $variant = ProductVariant::query()
                    ->where('label', $part['variant'])
                    ->whereRelation('product', 'slug', $part['product'])
                    ->first();

                $variant && $bundle->items()->create(['product_variant_id' => $variant->id, 'sort_order' => $index]);
            }
        }
    }
}
