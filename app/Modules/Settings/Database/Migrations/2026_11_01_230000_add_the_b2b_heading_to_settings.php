<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The heading on /ceramika-dla-gastronomii and the small line above it move from the template to the panel (MA-356):
 * Treści → Teksty na stronach → „Dla kawiarni i restauracji”. The texts are the ones the page showed so far;
 * a new line in the heading stands where the template had a <br>. An empty database is left to the seeder,
 * and a text already saved stays.
 */
return new class extends Migration
{
    private const TEXTS = [
        'text_b2b_eyebrow' => 'dla kawiarni, restauracji i hoteli',
        'text_b2b_heading' => "Naczynia, które\nwidać na zdjęciach\nTwoich gości",
    ];

    public function up(): void
    {
        if (! DB::table('settings')->exists()) {
            return;
        }

        foreach (self::TEXTS as $key => $text) {
            if (DB::table('settings')->where('key', $key)->doesntExist()) {
                DB::table('settings')->insert(['key' => $key, 'value' => json_encode($text), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // The texts may have been changed in the panel since, so they stay.
    }
};
