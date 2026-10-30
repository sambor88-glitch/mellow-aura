<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Skąd ten pomysł” (MA-292): what inspired a product, in Kasia's words, on its page and on the page of every piece
 * bought from it. Written in each language like the description.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('story')->nullable()->after('description');
        });

        Schema::table('product_translations', function (Blueprint $table) {
            $table->text('story')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('product_translations', function (Blueprint $table) {
            $table->dropColumn('story');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('story');
        });
    }
};
