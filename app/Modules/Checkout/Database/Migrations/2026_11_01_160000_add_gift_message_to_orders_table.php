<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A few words from the buyer to the person who gets the gift (MA-342). Kasia prints them on an A6 card for the parcel;
 * they never go into the buyer's confirmation, which is about the purchase, not the gift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('gift_message')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('gift_message');
        });
    }
};
