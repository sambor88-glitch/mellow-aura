<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kasia's first answers from the questionnaire (16.09.2026) correct sentences the prototype made up: children
 * from 5, work ready after 4 weeks, custom orders in 4 weeks, a plant imprint in 4–5 weeks, 6 people at every
 * workshop, no clay name and no „końcówki bel” until she confirms them, no promise about packaging and no
 * firing batch rules she hasn't decided. A sentence changes only while it still reads as seeded, so anything
 * rewritten in the panel stays as it is.
 */
return new class extends Migration
{
    /**
     * Setting key => [field inside each list item, or null for a plain value, seeded text, corrected text].
     *
     * @var array<string, list<array{?string, string, ?string}>>
     */
    private const CHANGES = [
        'material_tiles' => [
            ['text', 'Kamionka i szamot piaskowy. Nierówne krawędzie zostawiam, bo tak wygląda ręka.', 'Nierówne krawędzie zostawiam, bo tak wygląda ręka.'],
            ['text', 'Z drugiego obiegu i z końcówek bel. Jedna apaszka to jedna opaska.', 'Z drugiego obiegu.'],
        ],
        'faq_items' => [
            [
                'answer',
                'Rzeczy, które są na stanie, pakuję w 3–5 dni roboczych. Ceramika jedzie w papierze, wiórach i podwójnym kartonie — do tej pory nic nie dotarło stłuczone. Zamówienia indywidualne to około 3 tygodnie, bo każda praca przechodzi przez dwa wypały.',
                'Rzeczy, które są na stanie, pakuję w 3–5 dni roboczych. Zamówienia indywidualne to około 4 tygodnie, bo każda praca przechodzi przez dwa wypały.',
            ],
        ],
        'studio_facts' => [
            ['title', 'Od 6 lat', 'Od 5 lat'],
            ['text', 'Prace do odbioru po ~3 tygodniach.', 'Prace do odbioru po ~4 tygodniach.'],
        ],
        'imprint_steps' => [
            ['text', 'Trzy tygodnie. W paczce karta z datą wypału i nazwą rośliny, jeśli ją znasz.', 'Cztery, pięć tygodni. W paczce karta z datą wypału i nazwą rośliny, jeśli ją znasz.'],
        ],
        'workshop_types' => [
            ['group_label', 'grupa do 8 osób', 'grupa do 6 osób'],
            ['group_label', 'od 6 lat', 'od 5 lat, do 3 par'],
            ['summary', 'Krótko, konkretnie i bez presji — tyle, ile wytrzyma uwaga sześciolatka.', 'Krótko, konkretnie i bez presji — tyle, ile wytrzyma uwaga pięciolatka.'],
            ['includes', 'Odbiór po 3 tygodniach', 'Odbiór po 4 tygodniach'],
        ],
        'text_mug_lead_time' => [
            [null, 'gotowe w 3 tygodnie', 'gotowe w 4 tygodnie'],
        ],
        'text_kiln_note' => [
            [null, 'Wsad zbieram raz w tygodniu, zwykle w czwartek. Prace odbierzesz po 5–7 dniach. Nie przyjmuję gliny nieznanego pochodzenia ani prac grubszych niż 2 cm w ściance.', null],
        ],
    ];

    public function up(): void
    {
        foreach (self::CHANGES as $key => $changes) {
            $stored = DB::table('settings')->where('key', $key)->value('value');

            if (! is_string($stored)) {
                continue;
            }

            $value = json_decode($stored, true);
            $corrected = $value;

            foreach ($changes as [$field, $seeded, $correct]) {
                $corrected = $field === null
                    ? ($corrected === $seeded ? $correct : $corrected)
                    : $this->correctItems($corrected, $field, $seeded, $correct);
            }

            if ($corrected !== $value) {
                DB::table('settings')->where('key', $key)->update(['value' => json_encode($corrected), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // The seeded sentences were not true, so there is nothing to bring back.
    }

    private function correctItems(mixed $items, string $field, string $seeded, ?string $correct): mixed
    {
        if (! is_array($items)) {
            return $items;
        }

        return array_map(function (mixed $item) use ($field, $seeded, $correct) {
            if (! is_array($item) || ! array_key_exists($field, $item)) {
                return $item;
            }

            // A list inside the item, like what a workshop includes, is corrected line by line.
            $item[$field] = is_array($item[$field])
                ? array_map(fn (mixed $line) => $line === $seeded ? $correct : $line, $item[$field])
                : ($item[$field] === $seeded ? $correct : $item[$field]);

            return $item;
        }, $items);
    }
};
