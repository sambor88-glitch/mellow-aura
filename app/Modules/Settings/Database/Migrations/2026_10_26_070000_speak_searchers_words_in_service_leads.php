<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The first paragraphs of the two services and of custom orders speak the words people search with, not a potter's
 * (MA-108): what to do with the bouquet after the wedding, with grandma's scarf, an urn for a cat. Rewrites a text
 * only while it is still the seeded one — a paragraph Kasia changed in the panel stays hers.
 */
return new class extends Migration
{
    /** @var array<string, array{string, string}> key => [seeded text, new text] */
    private const CHANGES = [
        'text_scarf_lead' => [
            'Przyślij mi tkaninę, która coś dla Ciebie znaczy — jedwabną apaszkę po mamie, sukienkę z pierwszej pracy, koszulę, której nie da się już nosić. Uszyję z niej opaskę i scrunchies.',
            'Nie wiesz, co zrobić z apaszką po babci? Przyślij mi tkaninę, która coś dla Ciebie znaczy — jedwabną apaszkę po mamie, sukienkę z pierwszej pracy, koszulę, której nie da się już nosić. Przeszyję ją na opaskę i scrunchies z Twojej własnej tkaniny.',
        ],
        'text_imprint_lead' => [
            'Przyślij zasuszoną roślinę — kwiat z bukietu ślubnego, gałązkę z wieńca, listek z pierwszego wspólnego mieszkania. Odciskam ją w surowej glinie i wypalam.',
            'Nie wiesz, co zrobić z bukietem po weselu? Przyślij zasuszoną roślinę — kwiat z bukietu ślubnego, gałązkę z wieńca, listek z pierwszego wspólnego mieszkania. Odciskam ją w surowej glinie i wypalam. Zostaje naczynie, z którego jesz, a nie kwiat za szkłem — i taniej niż obraz z żywicy.',
        ],
        'text_custom_orders_lead' => [
            'Serwis na wesele, kubek z tekstem, którego nikt inny nie zrozumie, patera pod tort na chrzciny. Opowiedz mi, co masz w głowie — wycenę i szkic dostaniesz w ciągu pięciu dni.',
            'Serwis na wesele, kubek z tekstem, którego nikt inny nie zrozumie, urna dla kota, patera pod tort na chrzciny. Opowiedz mi, co masz w głowie — wycenę i szkic dostaniesz w ciągu pięciu dni.',
        ],
    ];

    public function up(): void
    {
        $this->swap(fn (array $texts) => $texts);
    }

    public function down(): void
    {
        $this->swap(fn (array $texts) => array_reverse($texts));
    }

    /**
     * @param  callable(array{string, string}): array{string, string}  $direction
     */
    private function swap(callable $direction): void
    {
        foreach (self::CHANGES as $key => $texts) {
            [$from, $to] = $direction($texts);
            $stored = DB::table('settings')->where('key', $key)->value('value');

            if (is_string($stored) && json_decode($stored) === $from) {
                DB::table('settings')->where('key', $key)->update(['value' => json_encode($to), 'updated_at' => now()]);
            }
        }
    }
};
