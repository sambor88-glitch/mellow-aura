<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The line under the logo in the footer, in Polish instead of the English one from Instagram (MA-209, Maciej's
 * choice 27.10). Only the untouched seeded line changes: a line Kasia already rewrote in the panel stays.
 */
return new class extends Migration
{
    private const ENGLISH = 'A little bit of magic. Home studio in Cracow. Ceramic & jewelry. Less waste silk scrunchies.';

    private const POLISH = 'Domowa pracownia w Krakowie. Ceramika formowana w dłoniach i jedwabne dodatki z drugiego obiegu.';

    public function up(): void
    {
        // Compared after decoding: MySQL and SQLite store the JSON column differently.
        $saved = DB::table('settings')->where('key', 'text_footer_tagline')->value('value');

        if ($saved !== null && json_decode($saved) === self::ENGLISH) {
            DB::table('settings')->where('key', 'text_footer_tagline')->update(['value' => json_encode(self::POLISH), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The line may have been changed in the panel since, so it stays.
    }
};
