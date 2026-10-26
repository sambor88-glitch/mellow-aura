<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The NBP average rate (table A) of each foreign currency, one row per currency, refreshed by catalog:euro-rate.
 * A product with euro_from_rate gets its euro prices from the złoty ones at this rate; any other product keeps
 * the euro prices typed in the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('currency', 3)->unique();
            // Złoty for one unit of the currency, four decimals as NBP publishes it.
            $table->decimal('rate', 10, 4);
            $table->date('effective_on');
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('euro_from_rate')->default(false)->after('is_exact_piece');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('euro_from_rate');
        });

        Schema::dropIfExists('exchange_rates');
    }
};
