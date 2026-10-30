<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A few words from Kasia about one bought piece (MA-292): what she thought while making it. Shown on the page of the
 * piece behind the QR code, in place of the quote every piece shares.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->text('maker_note')->nullable()->after('plant');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('maker_note');
        });
    }
};
