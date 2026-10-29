<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the variants of a product differ in (MA-257), so the card says „3 rozmiary” or „3 kwoty” instead of
 * „3 warianty” for everything. The seeded products get theirs here; the rest stay „warianty” until Kasia picks.
 */
return new class extends Migration
{
    private const KINDS = [
        'wazony' => 'sizes', 'patery' => 'sizes', 'talerze' => 'sizes', 'miski' => 'sizes',
        'kubki-malowane' => 'sizes', 'podstawki-pod-bizuterie' => 'sizes', 'scrunchies' => 'sizes',
        'jedwabne-opaski' => 'sizes', 'kosmetyczki' => 'sizes', 'piorniki' => 'sizes',
        'voucher-kwotowy' => 'amounts',
    ];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('variant_kind', 20)->default('options')->after('is_exact_piece');
        });

        foreach (self::KINDS as $slug => $kind) {
            DB::table('products')->where('slug', $slug)->update(['variant_kind' => $kind]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('variant_kind');
        });
    }
};
