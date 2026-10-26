<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prices of offers kept in the panel's price lists (workshops, firing, services), one row per change, so a
     * reduced price can show the lowest price from the 30 days before it. Product sizes have their own table.
     */
    public function up(): void
    {
        Schema::create('offer_price_history', function (Blueprint $table) {
            $table->id();
            // The list and the row, e.g. „workshop_types:lepienie_z_reki”.
            $table->string('offer', 160);
            $table->unsignedInteger('price_gross');
            $table->dateTime('valid_from');

            $table->index(['offer', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_price_history');
    }
};
