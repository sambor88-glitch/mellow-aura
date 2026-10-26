<?php

use App\Modules\Firing\Support\KilnPrices;
use App\Modules\Shared\Support\OfferPrices;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kasia's answers from the first round of the questionnaire (23.10.2026, MA-192): firing priced by the kilogram
 * (bisque 30 zł, low 1050°C 40 zł, high 1240°C 50 zł), a mug in 4–8 weeks and a large project in 6–12, clay
 * is chamotte and porcelain, ceramics go in the dishwasher and the microwave, orders are packed safely, the first
 * workshop is mug making for 2 hours, and euro prices from the NBP rate carry a 15% markup. A text changes only
 * while it still reads as seeded, so anything rewritten in the panel stays as it is.
 */
return new class extends Migration
{
    /**
     * Setting key => [field inside each list item, or null for a plain value, seeded text, corrected text].
     *
     * @var array<string, list<array{?string, string, string}>>
     */
    private const TEXTS = [
        'faq_items' => [
            [
                'answer',
                'Rzeczy, które są na stanie, pakuję w 3–5 dni roboczych. Zamówienia indywidualne to około 4 tygodnie, bo każda praca przechodzi przez dwa wypały.',
                'Rzeczy, które są na stanie, pakuję w 3–5 dni roboczych. Kubek z Twoim napisem to 4–8 tygodni, duże i skomplikowane projekty 6–12 tygodni, bo każda praca przechodzi przez dwa wypały.',
            ],
            [
                'answer',
                'Szkliwione naczynia bez złota myj spokojnie w zmywarce na krótkim programie. Wszystko ze złotą krawędzią — tylko ręcznie i nigdy w mikrofalówce, bo złoto to prawdziwy metal.',
                'Tak — szkliwione naczynia bez złota możesz myć w zmywarce i używać w mikrofalówce. Wszystko ze złotą krawędzią myj tylko ręcznie i nie wkładaj do mikrofalówki, bo złoto to prawdziwy metal.',
            ],
        ],
        'custom_order_steps' => [
            [
                'text',
                'Około czterech tygodni, przy dużych serwisach dłużej. Przed wysyłką dostajesz zdjęcia gotowej pracy. Jeśli coś pęknie w piecu, robię od nowa albo oddaję całą wpłatę.',
                'Kubek 4–8 tygodni, duże i skomplikowane projekty 6–12 tygodni. Przed wysyłką dostajesz zdjęcia gotowej pracy. Jeśli coś pęknie w piecu, robię od nowa albo oddaję całą wpłatę.',
            ],
        ],
        'text_mug_lead_time' => [
            [null, 'gotowe w 4 tygodnie', 'gotowe w 4–8 tygodni'],
        ],
        'workshop_types' => [
            ['name', 'Lepienie z ręki', 'Lepienie kubków'],
            ['duration_label', '2,5 godziny', '2 godziny'],
        ],
    ];

    /**
     * Tile title => [seeded text, corrected text]. Several tiles were seeded empty, so a tile is found by its title.
     *
     * @var array<string, array{?string, string}>
     */
    private const TILES = [
        'Glina' => ['Nierówne krawędzie zostawiam, bo tak wygląda ręka.', 'Szamot i porcelana. Nierówne krawędzie zostawiam, bo tak wygląda ręka.'],
        'Zmywarka' => [null, 'Można myć w zmywarce. Złocone rzeczy tylko ręcznie.'],
        'Mikrofalówka' => [null, 'Można używać w mikrofalówce, poza rzeczami ze złotem.'],
        'Pakowanie' => ['Papier pakowy i wióry albo inne wypełnienie. Wysyłam InPostem, z numerem do śledzenia.', 'Zamówienia są dobrze zabezpieczone przed wysyłką. Wysyłam InPostem, z numerem do śledzenia.'],
    ];

    /**
     * Firing rows as seeded by the litre, and what they are by the kilogram. The low firing is new and goes
     * between the two.
     *
     * @var array<string, array<string, mixed>>
     */
    private const KILN_SEEDED = [
        'bisque_firing' => ['label' => 'Wypał biskwitowy do 1000°C', 'price_gross' => 4000, 'unit' => 'litre'],
        'glaze_firing' => ['label' => 'Wypał na ostro do 1240°C', 'price_gross' => 6000, 'unit' => 'litre'],
    ];

    /** @var array<string, array<string, mixed>> */
    private const KILN_BY_WEIGHT = [
        'bisque_firing' => ['code' => 'bisque_firing', 'label' => 'Wypał biskwitowy', 'note' => 'liczony od wagi prac', 'price_gross' => 3000, 'unit' => 'kg', 'unit_label' => '/ kg'],
        'low_firing' => ['code' => 'low_firing', 'label' => 'Wypał niskotemperaturowy do 1050°C', 'note' => 'prace poszkliwione przez Ciebie', 'price_gross' => 4000, 'unit' => 'kg', 'unit_label' => '/ kg'],
        'glaze_firing' => ['code' => 'glaze_firing', 'label' => 'Wypał wysokotemperaturowy do 1240°C', 'note' => 'prace poszkliwione przez Ciebie', 'price_gross' => 5000, 'unit' => 'kg', 'unit_label' => '/ kg'],
    ];

    private const VOUCHER_EXPECT = [
        'Dwie i pół godziny przy stole w mojej pracowni, w grupie do sześciu osób.',
        'Dwie godziny przy stole w mojej pracowni, w grupie do sześciu osób.',
    ];

    private const EURO_MARKUP = 15;

    public function up(): void
    {
        // An empty database is left to the seeder, which already has the answers.
        if (! DB::table('settings')->exists()) {
            return;
        }

        foreach (self::TEXTS as $key => $changes) {
            $this->change($key, function (mixed $value) use ($changes) {
                foreach ($changes as [$field, $seeded, $correct]) {
                    $value = $field === null
                        ? ($value === $seeded ? $correct : $value)
                        : $this->correctItems($value, $field, $seeded, $correct);
                }

                return $value;
            });
        }

        $this->change('material_tiles', fn (mixed $tiles) => is_array($tiles) ? array_map(function (mixed $tile) {
            if (is_array($tile) && isset(self::TILES[$tile['title'] ?? null]) && ($tile['text'] ?? null) === self::TILES[$tile['title']][0]) {
                $tile['text'] = self::TILES[$tile['title']][1];
            }

            return $tile;
        }, $tiles) : $tiles);

        $this->change('voucher_workshop_notes', function (mixed $notes) {
            if (($notes['voucher-lepienie']['expect'] ?? null) === self::VOUCHER_EXPECT[0]) {
                $notes['voucher-lepienie']['expect'] = self::VOUCHER_EXPECT[1];
            }

            return $notes;
        });

        $this->kilnByWeight();

        if (! DB::table('settings')->where('key', 'euro_markup_percent')->exists()) {
            DB::table('settings')->insert(['key' => 'euro_markup_percent', 'value' => json_encode(self::EURO_MARKUP), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The seeded texts and the price per litre were not true, so there is nothing to bring back.
    }

    /**
     * Rows still priced by the litre as seeded become rows by the kilogram, with the low firing added after
     * the bisque. A row Kasia already changed stays, and so does the list if she has a low firing of her own.
     */
    private function kilnByWeight(): void
    {
        $before = KilnPrices::prices((array) $this->value(KilnPrices::LIST));

        $this->change(KilnPrices::LIST, function (mixed $rows) {
            if (! is_array($rows) || in_array('low_firing', array_column($rows, 'code'), true)) {
                return $rows;
            }

            $corrected = [];

            foreach ($rows as $row) {
                $seeded = self::KILN_SEEDED[$row['code'] ?? null] ?? null;
                $asSeeded = $seeded !== null && array_intersect_key($row, $seeded) == $seeded;
                $corrected[] = $asSeeded ? [...self::KILN_BY_WEIGHT[$row['code']], 'compare_at_price' => $row['compare_at_price'] ?? null] : $row;

                if ($asSeeded && $row['code'] === 'bisque_firing') {
                    $corrected[] = [...self::KILN_BY_WEIGHT['low_firing'], 'compare_at_price' => null];
                }
            }

            return $corrected;
        });

        // The new prices go to the price history, so a later reduction compares kilograms with kilograms.
        app(OfferPrices::class)->record(KilnPrices::LIST, $before, KilnPrices::prices((array) $this->value(KilnPrices::LIST)));
    }

    private function value(string $key): mixed
    {
        $stored = DB::table('settings')->where('key', $key)->value('value');

        return is_string($stored) ? json_decode($stored, true) : null;
    }

    /**
     * @param  callable(mixed): mixed  $correct
     */
    private function change(string $key, callable $correct): void
    {
        $value = $this->value($key);

        if ($value === null) {
            return;
        }

        $corrected = $correct($value);

        if ($corrected !== $value) {
            DB::table('settings')->where('key', $key)->update(['value' => json_encode($corrected), 'updated_at' => now()]);
        }
    }

    private function correctItems(mixed $items, string $field, string $seeded, string $correct): mixed
    {
        if (! is_array($items)) {
            return $items;
        }

        return array_map(function (mixed $item) use ($field, $seeded, $correct) {
            if (is_array($item) && ($item[$field] ?? null) === $seeded) {
                $item[$field] = $correct;
            }

            return $item;
        }, $items);
    }
};
