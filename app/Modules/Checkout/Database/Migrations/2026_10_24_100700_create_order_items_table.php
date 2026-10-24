<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Kept nullable so a deleted product does not take order history with it.
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            // Copies, not only keys: name, size and price can change after the sale.
            $table->string('product_name');
            $table->string('variant_label');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_gross');
            // Mug configurator: text and glaze as the customer chose them.
            $table->string('custom_text', 100)->nullable();
            $table->string('custom_glaze', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
