<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The seeder only adds what is missing, so the products from the prototype get the name of one piece here (MA-291).
 * Only where it is still empty and the product keeps its seeded name: a name changed in the panel may no longer
 * be a vase, and a piece name written by Kasia stays.
 */
return new class extends Migration
{
    /** slug => [seeded name, one piece, seeded English name, one piece in English] */
    private const PIECES = [
        'wazony' => ['Wazony', 'Wazon', 'Vases', 'Vase'],
        'patery' => ['Patery', 'Patera', 'Platters', 'Platter'],
        'talerze' => ['Talerze', 'Talerz', 'Plates', 'Plate'],
        'miski' => ['Miski', 'Miska', null, null],
        'kubki-malowane' => ['Kubki malowane ręcznie', 'Kubek malowany ręcznie', 'Hand-painted mugs', 'Hand-painted mug'],
    ];

    public function up(): void
    {
        foreach (self::PIECES as $slug => [$name, $piece, $englishName, $englishPiece]) {
            DB::table('products')->where('slug', $slug)->where('name', $name)->whereNull('piece_name')
                ->update(['piece_name' => $piece, 'updated_at' => now()]);

            if ($englishName === null) {
                continue;
            }

            $productId = DB::table('products')->where('slug', $slug)->value('id');

            DB::table('product_translations')->where('product_id', $productId)->where('locale', 'en')
                ->where('name', $englishName)->whereNull('piece_name')
                ->update(['piece_name' => $englishPiece, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The column goes with the previous migration; a name Kasia may have kept is not taken back here.
    }
};
