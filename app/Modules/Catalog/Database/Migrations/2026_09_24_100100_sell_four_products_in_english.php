<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The first four products on the English shop: vases, platters, plates and hand-painted mugs, with the English
 * text from catalog.json (working copy in Kasia's voice) and euro prices that follow the NBP rate. A product
 * that already has an English text keeps it. The euro prices appear once catalog:euro-rate has fetched a rate,
 * within the hour. An empty database is left to the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $catalog = json_decode(file_get_contents(base_path('app/Modules/Catalog/Database/Seeders/catalog.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($catalog['products'] as $row) {
            $product = isset($row['en']) ? DB::table('products')->where('slug', $row['slug'])->first() : null;

            if ($product === null) {
                continue;
            }

            DB::table('products')->where('id', $product->id)->update(['euro_from_rate' => true, 'updated_at' => now()]);

            $translated = DB::table('product_translations')->where('product_id', $product->id)->where('locale', 'en')->exists();
            $slugTaken = DB::table('product_translations')->where('locale', 'en')->where('slug', $row['en']['slug'])->exists();

            if (! $translated && ! $slugTaken) {
                DB::table('product_translations')->insert([...$row['en'], 'product_id' => $product->id, 'locale' => 'en', 'created_at' => now(), 'updated_at' => now()]);
            }

            foreach ($row['variants'] as $variant) {
                $id = DB::table('product_variants')->where('product_id', $product->id)->where('label', $variant['label'])->value('id');

                if ($id !== null && ! DB::table('product_variant_translations')->where('product_variant_id', $id)->where('locale', 'en')->exists()) {
                    DB::table('product_variant_translations')->insert(['product_variant_id' => $id, 'locale' => 'en', 'label' => $variant['label_en'], 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        // The English texts may have been edited in the panel since, so they stay.
    }
};
