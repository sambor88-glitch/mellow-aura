<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // The variant can disappear from the shop later; the copies below keep the order readable.
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_label');
            $table->unsignedSmallInteger('quantity');
            // Amounts in grosze, never float.
            $table->unsignedInteger('unit_price_gross');
            // The text and glaze for the mug, copied as the customer chose them.
            $table->string('custom_text')->nullable();
            $table->string('custom_glaze')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
