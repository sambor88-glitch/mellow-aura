<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A working euro price for the mug with a text, so it can go on the English shop before Kasia sets her euro price
 * list: €19 for every size. Only a size without a euro price gets it; a price typed in the panel stays. An empty
 * database is left to the seeder.
 */
return new class extends Migration
{
    private const EURO = 1900;

    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'mug_sizes')->first();
        $sizes = $row ? json_decode((string) $row->value, true) : null;

        if (! is_array($sizes)) {
            return;
        }

        $sizes = array_map(fn (mixed $size) => is_array($size) && ! is_numeric($size['price_eur'] ?? null)
            ? [...$size, 'price_eur' => self::EURO]
            : $size, $sizes);

        DB::table('settings')->where('key', 'mug_sizes')->update(['value' => json_encode($sizes, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The prices may have been changed in the panel since, so they stay.
    }
};
