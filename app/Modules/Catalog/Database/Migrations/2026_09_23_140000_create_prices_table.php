<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prices in currencies other than złoty. The złoty price stays in product_variants, the way Polish text stays in
 * the catalog tables; a row here holds one other currency, typed in by Kasia in the panel — never converted at
 * a daily rate. No row means the variant is not for sale in that currency and stays out of /en/.
 *
 * price_history learns the currency too: the lowest price from the 30 days before a reduction (Omnibus) is
 * a different number in złoty and in euro, because each changed on its own day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            // Cents, never float.
            $table->unsignedInteger('amount_minor');
            $table->unsignedInteger('compare_at_minor')->nullable();
            $table->timestamps();
            $table->unique(['product_variant_id', 'currency']);
        });

        Schema::table('price_history', function (Blueprint $table) {
            // Every row so far is a złoty price.
            $table->char('currency', 3)->default('PLN')->after('product_variant_id');
            $table->index(['product_variant_id', 'currency', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::table('price_history', function (Blueprint $table) {
            $table->dropIndex(['product_variant_id', 'currency', 'valid_from']);
            $table->dropColumn('currency');
        });

        Schema::dropIfExists('prices');
    }
};
