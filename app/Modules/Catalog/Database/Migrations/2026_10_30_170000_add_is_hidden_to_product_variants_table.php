<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A size taken off the shop without deleting it (MA-279): its price history, euro prices, translations and the people
 * waiting for it stay, and ticking it back brings it back as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('media_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });
    }
};
