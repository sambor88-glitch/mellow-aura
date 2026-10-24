<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Short text above the product list on /sklep/{category}.
            $table->text('intro')->nullable()->after('name');
            // Care rule for every product in the category; a product's care_note replaces it.
            $table->string('default_care_note')->nullable()->after('intro');
        });

        Schema::table('products', function (Blueprint $table) {
            // "Opis dla Google" from the panel; empty means the start of the description.
            $table->string('seo_description')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('seo_description');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['intro', 'default_care_note']);
        });
    }
};
