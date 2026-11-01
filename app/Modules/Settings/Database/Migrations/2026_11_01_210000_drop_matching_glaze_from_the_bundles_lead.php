<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The lead on „Zestawy prezentowe” without „Dobieram odcień szkliwa do tkaniny”: the mug and the scrunchie
 * no longer come in one colour (MA-347, Maciej's decision 1.11). Only the untouched seeded lead changes:
 * a lead Kasia already rewrote in the panel stays.
 */
return new class extends Migration
{
    private const OLD = 'Zestawy, których nie znajdziesz w innej pracowni — bo mało kto robi jednocześnie ceramikę i rękodzieło tekstylne. Dobieram odcień szkliwa do tkaniny, nie odwrotnie.';

    private const NEW = 'Zestawy, których nie znajdziesz w innej pracowni — bo mało kto robi jednocześnie ceramikę i rękodzieło tekstylne.';

    public function up(): void
    {
        // Compared after decoding: MySQL and SQLite store the JSON column differently.
        $saved = DB::table('settings')->where('key', 'text_bundles_lead')->value('value');

        if ($saved !== null && json_decode($saved) === self::OLD) {
            DB::table('settings')->where('key', 'text_bundles_lead')->update(['value' => json_encode(self::NEW), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The lead may have been changed in the panel since, so it stays.
    }
};
