<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * English names for the categories already in the database (the seeder gives them to a fresh one). Vouchers stay
 * Polish-only, like the workshops they pay for. A category added in the panel later gets no name here and stays out
 * of /en/ until it has one.
 */
return new class extends Migration
{
    private const NAMES = [
        'kubki-i-filizanki' => ['Mugs & cups', 'mugs-and-cups'],
        'talerze-i-miski' => ['Plates & bowls', 'plates-and-bowls'],
        'wazony-i-patery' => ['Vases & platters', 'vases-and-platters'],
        'kadzielnice-i-podstawki' => ['Incense holders & coasters', 'incense-holders-and-coasters'],
        'bizuteria' => ['Jewellery', 'jewellery'],
        'jedwab' => ['Silk', 'silk'],
        'kosmetyczki-i-piorniki' => ['Pouches & pencil cases', 'pouches-and-pencil-cases'],
    ];

    public function up(): void
    {
        foreach (DB::table('categories')->whereIn('slug', array_keys(self::NAMES))->get(['id', 'slug']) as $category) {
            [$name, $slug] = self::NAMES[$category->slug];

            DB::table('category_translations')->insertOrIgnore([
                'category_id' => $category->id,
                'locale' => 'en',
                'name' => $name,
                'slug' => $slug,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('category_translations')->where('locale', 'en')->whereIn('slug', array_column(self::NAMES, 1))->delete();
    }
};
