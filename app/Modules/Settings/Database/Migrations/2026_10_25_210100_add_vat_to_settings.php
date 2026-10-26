<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The VAT rate every order item records, and the legal basis printed with a zero rate. Kasia sells under the
 * subjective exemption; when that ends (the Polish limit, or WSTO above 10 000 EUR) the accountant says what changes.
 * An empty database is left to the seeder, and values already saved stay.
 */
return new class extends Migration
{
    private const VAT = ['vat_rate' => 0, 'vat_exemption' => 'zwolnienie podmiotowe, art. 113 ust. 1 ustawy o VAT'];

    public function up(): void
    {
        if (! DB::table('settings')->exists()) {
            return;
        }

        $missing = array_diff_key(self::VAT, array_flip(DB::table('settings')->whereIn('key', array_keys(self::VAT))->pluck('key')->all()));

        foreach ($missing as $key => $value) {
            DB::table('settings')->insert(['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The values may have been changed since, so they stay.
    }
};
