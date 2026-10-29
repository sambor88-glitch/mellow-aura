<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Texts about Kasia and the studio without English words („home studio”, „twórczy flow”) and with the long dash
 * as the only dash between words (MA-260). Only an untouched seeded text changes: one Kasia rewrote in the panel stays.
 */
return new class extends Migration
{
    /** Key => [seeded text, Polish text]. */
    private const TEXTS = [
        'text_about_paragraph_1' => [
            'Tworzę MellowAura – kameralną pracownię, w której powstają przedmioty z duszą. To miejsce, gdzie sztuka spotyka się z uważnością, a codzienność nabiera miękkich, zmysłowych kształtów.',
            'Tworzę MellowAura — kameralną pracownię, w której powstają przedmioty z duszą. To miejsce, gdzie sztuka spotyka się z uważnością, a codzienność nabiera miękkich, zmysłowych kształtów.',
        ],
        'text_about_paragraph_2' => [
            'Zajmuję się ceramiką i rękodziełem tekstylnym – każda z tych dziedzin daje mi coś innego, ale łączy je to samo: praca w skupieniu, z dbałością o detale, fakturę i jakość materiału. W mojej pracowni powstają ręcznie formowane naczynia, autorskie formy użytkowe, ale także opaski, scrunchie i ozdoby do włosów, szyte z wyselekcjonowanych, naturalnych tkanin – takich jak jedwab, len czy bawełna.',
            'Zajmuję się ceramiką i rękodziełem tekstylnym — każda z tych dziedzin daje mi coś innego, ale łączy je to samo: praca w skupieniu, z dbałością o detale, fakturę i jakość materiału. W mojej pracowni powstają ręcznie formowane naczynia, autorskie formy użytkowe, ale także opaski, scrunchie i ozdoby do włosów, szyte z wyselekcjonowanych, naturalnych tkanin — takich jak jedwab, len czy bawełna.',
        ],
        'text_about_paragraph_3' => [
            'Lubię łączyć surowość z miękkością, prostotę z symboliką, dotyk z emocją. MellowAura to zaproszenie do spokoju i piękna – zaklętego w przedmiotach, które towarzyszą nam na co dzień.',
            'Lubię łączyć surowość z miękkością, prostotę z symboliką, dotyk z emocją. MellowAura to zaproszenie do spokoju i piękna — zaklętego w przedmiotach, które towarzyszą nam na co dzień.',
        ],
        'text_studio_lead' => [
            'Warsztaty, które uczą nie tylko ceramiki – ale też uważności i słuchania siebie. Zanurz dłonie w glinie – poczuj spokój, radość i twórczy flow.',
            'Warsztaty, które uczą nie tylko ceramiki — ale też uważności i słuchania siebie. Zanurz dłonie w glinie — poczuj spokój, radość i twórczą swobodę.',
        ],
        'text_studio_paragraph_2' => [
            'Dokładny adres podaję po zapisaniu się — to home studio, więc wpuszczam po jednej grupie naraz. Na miejscu jest herbata, fartuchy i muzyka, którą możesz zmienić.',
            'Dokładny adres podaję po zapisaniu się — to domowa pracownia, więc wpuszczam po jednej grupie naraz. Na miejscu jest herbata, fartuchy i muzyka, którą możesz zmienić.',
        ],
    ];

    public function up(): void
    {
        foreach (self::TEXTS as $key => [$old, $new]) {
            // Compared after decoding: MySQL and SQLite store the JSON column differently.
            $saved = DB::table('settings')->where('key', $key)->value('value');

            if ($saved !== null && json_decode($saved) === $old) {
                DB::table('settings')->where('key', $key)->update(['value' => json_encode($new), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // The texts may have been changed in the panel since, so they stay.
    }
};
