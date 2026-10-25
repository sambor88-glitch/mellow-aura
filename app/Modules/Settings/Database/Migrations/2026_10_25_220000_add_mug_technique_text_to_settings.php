<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The sentences under the mug configurator about how the text is made and why it doesn't wash off (MA-113).
 * A draft in Kasia's voice, built only from what her other mug texts already say — she confirms or rewrites it
 * in the panel. An empty database is left to the seeder, and a text already saved stays.
 */
return new class extends Migration
{
    private const TEXT = 'Każdą literę wbijam stemplem osobno, zanim glina wyschnie. Po wypale napis jest wgłębiony w ściankę, a nie nałożony na nią — pod światło widać, jak głęboko sięga. Nadruk i farba z czasem blakną i schodzą w zmywarce. Wbitego napisu nie ma czego zetrzeć.';

    public function up(): void
    {
        if (! DB::table('settings')->exists() || DB::table('settings')->where('key', 'text_mug_technique')->exists()) {
            return;
        }

        DB::table('settings')->insert(['key' => 'text_mug_technique', 'value' => json_encode(self::TEXT), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The text may have been changed in the panel since, so it stays.
    }
};
