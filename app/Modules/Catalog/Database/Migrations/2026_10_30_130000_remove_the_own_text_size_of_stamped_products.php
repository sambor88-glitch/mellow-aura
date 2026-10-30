<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * „Twój tekst” on a stamped product (the quote mugs) is gone (MA-275, MA-277): a mug with the customer's own text is
 * ordered in the mug configurator. The size goes as if Kasia removed it in the panel — an order keeps its copy of the
 * name, price and text, and only loses the link to the size.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_variants')
            ->whereNull('stock')
            ->whereIn('product_id', DB::table('products')->where('stamp_enabled', true)->select('id'))
            ->delete();
    }
};
