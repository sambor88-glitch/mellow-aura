<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a product goes to Google's free shopping results, and its kind in Google's taxonomy.
     * The products from the prototype get their kind here, so a database seeded earlier needs no new seed.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('google_category')->nullable()->after('safety_warnings');
            $table->boolean('show_in_google')->default(true)->after('google_category');
        });

        foreach ([
            'kubki-z-cytatem' => 2169, 'kubki-malowane' => 2169, 'zestaw-4-filizanek' => 6049, 'talerze' => 3553, 'miski' => 3498,
            'patery' => 4372, 'wazony' => 602, 'kadzielnice' => 4741, 'podstawki-pod-bizuterie' => 5974, 'bizuteria' => 188,
            'scrunchies' => 1483, 'jedwabne-opaski' => 1662, 'kosmetyczki' => 108, 'piorniki' => 3062,
            'voucher-lepienie' => 53, 'voucher-para' => 53, 'voucher-kwotowy' => 53,
        ] as $slug => $category) {
            DB::table('products')->where('slug', $slug)->whereNull('google_category')->update(['google_category' => $category]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['google_category', 'show_in_google']);
        });
    }
};
