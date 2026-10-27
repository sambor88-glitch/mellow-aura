<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The workshop and the seats a voucher books (MA-137), copied from its product when it is issued, like the order item
 * copies the name and the price. Empty for an amount voucher and for one Kasia has not filled in yet in the panel:
 * such a code books nothing on the site. Vouchers issued before get theirs from the product they were bought as.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Indexed: the booking page asks whether any voucher is for its workshop.
            $table->string('workshop_code', 40)->nullable()->index()->after('dedication');
            $table->unsignedTinyInteger('seats')->nullable()->after('workshop_code');
        });

        $issued = DB::table('vouchers')
            ->join('order_items', 'order_items.id', '=', 'vouchers.order_item_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNotNull('products.workshop_code')
            ->get(['vouchers.id', 'products.workshop_code', 'product_variants.voucher_seats']);

        foreach ($issued as $voucher) {
            DB::table('vouchers')->where('id', $voucher->id)->update(['workshop_code' => $voucher->workshop_code, 'seats' => $voucher->voucher_seats ?? 1]);
        }
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['workshop_code']);
            $table->dropColumn(['workshop_code', 'seats']);
        });
    }
};
