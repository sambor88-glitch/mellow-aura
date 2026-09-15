<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Seeder;

/**
 * The offer from the prototype (catalog.json). It only adds what is missing,
 * so running it again never overwrites changes made in the panel.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__.'/catalog.json'), true, flags: JSON_THROW_ON_ERROR);

        $categories = collect($data['categories'])->mapWithKeys(fn (array $category) => [
            $category['slug'] => Category::firstOrCreate(['slug' => $category['slug']], $category),
        ]);

        foreach ($data['products'] as $row) {
            $product = Product::firstOrCreate(['slug' => $row['slug']], [
                ...collect($row)->except(['category', 'variants', 'images'])->all(),
                'category_id' => $categories[$row['category']]->id,
            ]);

            foreach ($row['variants'] as $variant) {
                $product->variants()->firstOrCreate(['label' => $variant['label']], $variant);
            }
        }
    }
}
