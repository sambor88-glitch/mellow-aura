<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every price change is a new row. The shop reads the lowest price from
        // the 30 days before a discount from here (Omnibus directive).
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_gross');
            $table->timestamp('valid_from');

            $table->index(['product_variant_id', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_history');
    }
};
