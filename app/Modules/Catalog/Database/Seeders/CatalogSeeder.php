<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The offer from the prototype (catalog.json). It only adds what is missing,
 * so running it again never overwrites changes made in the panel.
 * Photos are copied from zdjecia/, never moved.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__.'/catalog.json'), true, flags: JSON_THROW_ON_ERROR);

        $categories = collect($data['categories'])->mapWithKeys(fn (array $category) => [
            $category['slug'] => Category::firstOrCreate(['slug' => $category['slug']], collect($category)->except('en')->all()),
        ]);

        // The English names; a category without them (vouchers for workshops) stays Polish-only.
        foreach ($data['categories'] as $category) {
            if (isset($category['en'])) {
                $categories[$category['slug']]->translations()->firstOrCreate(['locale' => 'en'], $category['en']);
            }
        }

        foreach ($data['products'] as $row) {
            $product = Product::firstOrCreate(['slug' => $row['slug']], [
                ...collect($row)->except(['category', 'variants', 'images'])->all(),
                'category_id' => $categories[$row['category']]->id,
            ]);

            foreach ($row['images'] as $image) {
                $existing = $product->getMedia('images')->firstWhere('file_name', basename($image['path']));

                if ($existing && Storage::disk($existing->disk)->exists($existing->getPathRelativeToRoot())) {
                    continue;
                }

                // The record outlived its file (e.g. a server without shared storage), so add the photo again.
                $existing?->delete();

                $product->addMedia(base_path($image['path']))
                    ->preservingOriginal()
                    ->withCustomProperties(['alt' => $image['alt']])
                    ->toMediaCollection('images');
            }

            // After the photos, so a size can point at its own one (a quote mug with its lettering).
            $photos = $product->load('media')->getMedia('images');

            foreach ($row['variants'] as $variant) {
                $photo = isset($variant['image']) ? $photos->firstWhere('file_name', basename($variant['image'])) : null;
                $saved = $product->variants()->firstOrCreate(['label' => $variant['label']], [
                    ...collect($variant)->except('image')->all(),
                    'media_id' => $photo?->id,
                ]);

                // A photo added again above took its link with it; put it back.
                if ($photo !== null && $saved->media_id === null) {
                    $saved->update(['media_id' => $photo->id]);
                }
            }
        }
    }
}
