<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the terms of sale (§4) and the EU product safety rules (GPSR) want on the product page.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('food_contact')->nullable()->after('care_note');
            // A feature nobody would expect, e.g. „nie do zmywarki”: the customer accepts it separately at checkout.
            $table->string('deviation')->nullable()->after('food_contact');
            // How much a handmade piece may differ from the dimensions on the page; empty means the one from the settings.
            $table->string('size_tolerance')->nullable()->after('deviation');
            $table->text('safety_warnings')->nullable()->after('size_tolerance');
            // „Ta sztuka”: the photos show the very piece the customer gets.
            $table->boolean('is_exact_piece')->default(false)->after('is_one_off');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['food_contact', 'deviation', 'size_tolerance', 'safety_warnings', 'is_exact_piece']);
        });
    }
};
