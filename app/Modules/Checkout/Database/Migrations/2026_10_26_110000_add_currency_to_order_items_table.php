<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each item says which currency its price is in (MA-191), so a line copied out of an order — into a report, an
 * invoice or the WSTO counter — never loses it. An item is always in the currency of its order; the items already
 * saved take theirs from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->char('currency', 3)->default('PLN')->after('unit_price_gross');
        });

        DB::table('order_items')->update([
            'currency' => DB::raw('(select orders.currency from orders where orders.id = order_items.order_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
