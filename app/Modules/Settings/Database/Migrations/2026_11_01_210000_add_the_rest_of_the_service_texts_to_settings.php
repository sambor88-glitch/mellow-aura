<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The rest of the sentences on the service pages (MA-348): the line above the heading, the button, the sentence under
 * „Przed i po”, the heading of the price list and the frame next to it. Kasia changes them in the panel (Usługi → Teksty);
 * an empty one drops out of the page. They start with what the pages said until now, so nothing disappears. An empty
 * database is left to the seeder, and a text already saved stays.
 */
return new class extends Migration
{
    private const TEXTS = [
        'text_scarf_eyebrow' => 'usługa · z Twojej tkaniny',
        'text_scarf_cta' => 'Napisz, co chcesz przysłać',
        'text_scarf_examples_lead' => 'Tkaniny, które do mnie przyszły, obok tego, co z nich uszyłam.',
        'text_scarf_prices_heading' => 'Co wychodzi z jednej apaszki',
        'text_scarf_card_eyebrow' => 'dlaczego to robię',
        'text_scarf_card_heading' => 'Najładniejsze wzory, jakie przeszły mi przez ręce, były z cudzych szaf.',
        'text_scarf_card_text' => 'Stare apaszki mają desenie, których nie kupisz na metry — i historię, której nie da się dodrukować. Szkoda, żeby to leżało.',
        'text_imprint_eyebrow' => 'usługa · z Twojej rośliny',
        'text_imprint_cta' => 'Napisz, co chcesz odcisnąć',
        'text_imprint_examples_lead' => 'Rośliny, które do mnie przyszły, obok ceramiki, na której zostawiły ślad.',
        'text_imprint_prices_heading' => 'Na czym odciskam',
        'text_imprint_card_eyebrow' => 'na co to zamawiają',
        'text_imprint_card_list' => "Bukiet ślubny, który miał uschnąć w wazonie\nKwiaty z pożegnania kogoś bliskiego\nZioła z pierwszego własnego balkonu\nListek z drzewa zasadzonego przy narodzinach",
        'text_imprint_card_text' => 'Piszą do mnie zwykle tydzień po wydarzeniu, kiedy kwiaty jeszcze da się uratować. Jeśli Twoje już zasychają — nie czekaj z wiadomością.',
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
