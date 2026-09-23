<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The site used to promise „3–5 dni roboczych” straight from the code; now the days are a setting. A database seeded
 * before the setting existed gets the same 3 and 5 days, so the promise doesn't disappear after the deploy.
 * An empty database is left to the seeder, and days already saved in the panel stay.
 */
return new class extends Migration
{
    private const DAYS = ['dispatch_days_min' => 3, 'dispatch_days_max' => 5];

    public function up(): void
    {
        if (! DB::table('settings')->exists()) {
            return;
        }

        $missing = array_diff_key(self::DAYS, array_flip(DB::table('settings')->whereIn('key', array_keys(self::DAYS))->pluck('key')->all()));

        foreach ($missing as $key => $days) {
            DB::table('settings')->insert(['key' => $key, 'value' => json_encode($days), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The days may have been changed in the panel since, so they stay.
    }
};
