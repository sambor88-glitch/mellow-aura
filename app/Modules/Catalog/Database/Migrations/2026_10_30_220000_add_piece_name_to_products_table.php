<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Nazwa jednej sztuki” (MA-291): a product is a kind of thing with sizes, named in the plural („Wazony”), while the page
 * of one bought piece and its certificate speak of one („Wazon”). Written in each language like the name; empty means
 * the piece keeps the product's name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('piece_name')->nullable()->after('name');
        });

        Schema::table('product_translations', function (Blueprint $table) {
            $table->string('piece_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('product_translations', function (Blueprint $table) {
            $table->dropColumn('piece_name');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('piece_name');
        });
    }
};
