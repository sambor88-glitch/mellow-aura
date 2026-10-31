<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Facts the pages used to carry in the code (MA-297): how soon Kasia answers, how long BLIK takes, how she packs,
 * the materials and the sentences about the dishwasher and the shelf. Kasia changes them in the panel (Treści → Teksty
 * na stronach); an empty one drops out of the page. They start with what the pages said until now, so nothing
 * disappears. An empty database is left to the seeder, and a text already saved stays.
 */
return new class extends Migration
{
    private const TEXTS = [
        'text_home_lead_mug' => 'Do tego kubki z napisem wbijanym stemplem, którego nie zetrze żadna zmywarka.',
        'text_home_fact_handmade' => '100%',
        'text_home_materials' => "glina\njedwab\nlen\nzłoto 24k",
        'text_home_card_b2b' => 'Powtarzalne formy, które wytrzymują zmywarkę i codzienny serwis.',
        'text_shop_intro_note' => 'Ceramika jest wypalona i gotowa do wysyłki — jeśli czegoś nie ma, znaczy, że już pojechało do kogoś.',
        'text_reply_time' => 'Odpisuję zwykle tego samego dnia.',
        'text_blik_time' => '10 sekund',
        'text_checkout_packing' => 'Ceramikę zawijam w wióry i podwójny karton',
    ];

    public function up(): void
    {
        if (! DB::table('settings')->exists()) {
            return;
        }

        $saved = DB::table('settings')->whereIn('key', array_keys(self::TEXTS))->pluck('key')->all();

        foreach (array_diff_key(self::TEXTS, array_flip($saved)) as $key => $text) {
            DB::table('settings')->insert(['key' => $key, 'value' => json_encode($text), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The texts may have been changed in the panel since, so they stay.
    }
};
