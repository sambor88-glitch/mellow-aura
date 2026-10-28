<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An amount voucher works like a gift card (MA-211): it is worth amount_minor when issued and keeps balance_minor,
 * in grosze, always in złoty. Every use is a row in voucher_redemptions: held while the order waits for its payment,
 * used once it is paid, returned when the order is abandoned or Kasia gives the money back to the voucher.
 * Both stay empty for a workshop voucher, which books seats instead.
 *
 * Amount vouchers issued before get their worth from the order item they were bought as; one Kasia already marked
 * used by hand has nothing left.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->unsignedInteger('amount_minor')->nullable()->after('seats');
            $table->unsignedInteger('balance_minor')->nullable()->after('amount_minor');
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_minor');
            // held, used or returned (Gifts\Enums\RedemptionStatus)
            $table->string('status', 16);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        $issued = DB::table('vouchers')
            ->join('order_items', 'order_items.id', '=', 'vouchers.order_item_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('vouchers.workshop_code')
            ->whereNull('products.workshop_code')
            ->where('order_items.currency', 'PLN')
            ->get(['vouchers.id', 'vouchers.redeemed_at', 'order_items.unit_price_gross']);

        foreach ($issued as $voucher) {
            DB::table('vouchers')->where('id', $voucher->id)->update([
                'amount_minor' => $voucher->unit_price_gross,
                'balance_minor' => $voucher->redeemed_at === null ? $voucher->unit_price_gross : 0,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['amount_minor', 'balance_minor']);
        });
    }
};
