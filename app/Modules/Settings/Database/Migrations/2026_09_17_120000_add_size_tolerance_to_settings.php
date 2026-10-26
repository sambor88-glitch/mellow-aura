<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kasia confirmed on 16.09.2026 that a handmade piece may differ from its dimensions by up to 0,5 cm. A database
 * seeded before the setting existed gets that value; an empty database is left to the seeder, and a value
 * already saved in the panel stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('settings')->exists() || DB::table('settings')->where('key', 'size_tolerance')->exists()) {
            return;
        }

        DB::table('settings')->insert(['key' => 'size_tolerance', 'value' => json_encode('0,5 cm'), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // A value changed in the panel since then stays.
    }
};
