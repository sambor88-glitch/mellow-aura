<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * How many days after the delivery the customer gets the mail asking for a Google review (MA-329). The link to
 * the review form is added in the panel once the Google Business Profile exists; until then no mail goes out.
 * An empty database is left to the seeder, and a value already saved stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('settings')->exists() || DB::table('settings')->where('key', 'review_request_days')->exists()) {
            return;
        }

        DB::table('settings')->insert(['key' => 'review_request_days', 'value' => json_encode(7), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The value may have been changed since, so it stays.
    }
};
