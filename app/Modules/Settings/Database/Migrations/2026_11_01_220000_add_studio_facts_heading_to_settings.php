<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The heading of the facts card on /pracownia (MA-355). Until now it was written in the view; Kasia changes it in the
 * panel (Treści → Pracownia) and an empty one leaves the card without a heading. It starts with what the page said,
 * so nothing changes. An empty database is left to the seeder, and a heading already saved stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('settings')->exists() || DB::table('settings')->where('key', 'studio_facts_heading')->exists()) {
            return;
        }

        DB::table('settings')->insert(['key' => 'studio_facts_heading', 'value' => json_encode('Dobrze wiedzieć'), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The heading may have been changed in the panel since, so it stays.
    }
};
