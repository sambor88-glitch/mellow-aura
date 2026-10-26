<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Working euro prices for delivery, so the English checkout has something to offer before the carrier's price list
 * is in (MA-124): about 50 zł, €12, for the parcel locker and the courier, and pickup at the studio free. Only a
 * method without a euro price gets one; a price Kasia typed in the panel stays. An empty database is left to the
 * seeder.
 */
return new class extends Migration
{
    private const EURO = ['parcel_locker' => 1200, 'courier' => 1200, 'studio_pickup' => 0];

    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'shipping_methods')->first();
        $methods = $row ? json_decode((string) $row->value, true) : null;

        if (! is_array($methods)) {
            return;
        }

        $methods = array_map(fn (mixed $method) => is_array($method) && array_key_exists($method['code'] ?? '', self::EURO) && ! is_numeric($method['price_eur'] ?? null)
            ? [...$method, 'price_eur' => self::EURO[$method['code']]]
            : $method, $methods);

        DB::table('settings')->where('key', 'shipping_methods')->update(['value' => json_encode($methods, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The prices may have been changed in the panel since, so they stay.
    }
};
