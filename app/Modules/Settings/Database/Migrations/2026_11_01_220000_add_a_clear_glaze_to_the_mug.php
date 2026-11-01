<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * „Transparentne”, the clay's own colour, joins the mug's inside colours (MA-350, Maciej's request 1.11). It goes
 * at the end, so the glaze a customer sees first stays the same; Kasia moves it up in the panel after a test firing.
 * A list that already has it stays as it is.
 */
return new class extends Migration
{
    private const CLEAR = ['code' => 'transparent', 'name' => 'Transparentne', 'hex' => '#D9C4A8'];

    public function up(): void
    {
        $saved = DB::table('settings')->where('key', 'mug_glazes')->value('value');

        if ($saved === null) {
            return;
        }

        $glazes = json_decode($saved, true);

        if (! is_array($glazes) || collect($glazes)->contains(fn (mixed $glaze) => is_array($glaze) && ($glaze['code'] ?? null) === self::CLEAR['code'])) {
            return;
        }

        DB::table('settings')->where('key', 'mug_glazes')->update(['value' => json_encode([...$glazes, self::CLEAR]), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Kasia may have changed the colours in the panel since, so they stay.
    }
};
