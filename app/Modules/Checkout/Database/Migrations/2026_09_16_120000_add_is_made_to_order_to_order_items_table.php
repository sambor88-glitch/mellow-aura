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
        Schema::table('order_items', function (Blueprint $table) {
            // Made for this order, like gift wrapping or a mug from the configurator: nothing comes off a shelf,
            // so an item without a variant is not a missing piece.
            $table->boolean('is_made_to_order')->default(false)->after('missing_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('is_made_to_order');
        });
    }
};
