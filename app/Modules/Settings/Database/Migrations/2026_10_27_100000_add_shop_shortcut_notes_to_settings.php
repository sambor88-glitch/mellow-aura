<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The line under each shortcut card above the shop (MA-207). Until now it lived in the language files; Maciej
 * accepted these on 27.10 and Kasia changes them in the panel (Kategorie). An empty database is left to the seeder,
 * and a line already saved stays.
 */
return new class extends Migration
{
    private const NOTES = [
        'shop_shortcut_note_mug' => 'Twój tekst, Twój kolor',
        'shop_shortcut_note_bundles' => 'Gotowe do wręczenia',
        'shop_shortcut_note_gift_finder' => 'Pomogę wybrać',
        'shop_shortcut_note_scarf' => 'Opaska z Twojej tkaniny',
        'shop_shortcut_note_imprint' => 'Twoje kwiaty w ceramice',
        'shop_shortcut_note_custom_orders' => 'Opisz pomysł, wycenię',
        'shop_shortcut_note_mug_en' => 'Your words, your colour',
        'shop_shortcut_note_bundles_en' => 'Ready to give',
        'shop_shortcut_note_gift_finder_en' => 'I will help you choose',
        'shop_shortcut_note_scarf_en' => 'A headband from your fabric',
        'shop_shortcut_note_imprint_en' => 'Your flowers in clay',
        'shop_shortcut_note_custom_orders_en' => 'Describe it, I will quote',
    ];

    public function up(): void
    {
        if (! DB::table('settings')->exists()) {
            return;
        }

        $saved = DB::table('settings')->whereIn('key', array_keys(self::NOTES))->pluck('key')->all();

        foreach (array_diff_key(self::NOTES, array_flip($saved)) as $key => $text) {
            DB::table('settings')->insert(['key' => $key, 'value' => json_encode($text), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The lines may have been changed in the panel since, so they stay.
    }
};
