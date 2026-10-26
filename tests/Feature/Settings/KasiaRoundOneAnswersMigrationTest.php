<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Shared\Models\OfferPriceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasiaRoundOneAnswersMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'app/Modules/Settings/Database/Migrations/2026_10_26_100000_apply_kasias_round_one_answers_to_settings.php';

    public function test_seeded_texts_and_litre_prices_get_kasias_answers(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->settings([
            'kiln_prices' => [
                ['code' => 'bisque_firing', 'label' => 'Wypał biskwitowy do 1000°C', 'note' => 'liczony od litra zajętego miejsca', 'price_gross' => 4000, 'unit' => 'litre', 'unit_label' => '/ l'],
                ['code' => 'glaze_firing', 'label' => 'Wypał na ostro do 1240°C', 'note' => 'prace poszkliwione przez Ciebie', 'price_gross' => 6000, 'unit' => 'litre', 'unit_label' => '/ l'],
                ['code' => 'gold_firing', 'label' => 'Wypał złota, trzeci wypał', 'note' => '750°C, osobny wsad', 'price_gross' => 3500, 'unit' => 'piece', 'unit_label' => '/ szt.'],
            ],
            'material_tiles' => [
                ['title' => 'Glina', 'text' => 'Nierówne krawędzie zostawiam, bo tak wygląda ręka.'],
                ['title' => 'Temperatura wypału', 'text' => null],
                ['title' => 'Zmywarka', 'text' => null],
                ['title' => 'Mikrofalówka', 'text' => null],
                ['title' => 'Atest szkliw', 'text' => null],
                ['title' => 'Pakowanie', 'text' => 'Papier pakowy i wióry albo inne wypełnienie. Wysyłam InPostem, z numerem do śledzenia.'],
            ],
            'faq_items' => [['question' => 'Jak długo czekam?', 'answer' => 'Rzeczy, które są na stanie, pakuję w 3–5 dni roboczych. Zamówienia indywidualne to około 4 tygodnie, bo każda praca przechodzi przez dwa wypały.']],
            'text_mug_lead_time' => 'gotowe w 4 tygodnie',
            'workshop_types' => [['code' => 'hand_building', 'name' => 'Lepienie z ręki', 'duration_label' => '2,5 godziny', 'price_gross' => 22000]],
            'voucher_workshop_notes' => ['voucher-lepienie' => ['expect' => 'Dwie i pół godziny przy stole w mojej pracowni, w grupie do sześciu osób.']],
        ]);
        Setting::query()->where('key', 'euro_markup_percent')->delete();

        (require base_path(self::MIGRATION))->up();

        $this->assertSame([
            ['bisque_firing', 'Wypał biskwitowy', 3000, 'kg', '/ kg'],
            ['low_firing', 'Wypał niskotemperaturowy do 1050°C', 4000, 'kg', '/ kg'],
            ['glaze_firing', 'Wypał wysokotemperaturowy do 1240°C', 5000, 'kg', '/ kg'],
            ['gold_firing', 'Wypał złota, trzeci wypał', 3500, 'piece', '/ szt.'],
        ], array_map(fn (array $row) => [$row['code'], $row['label'], $row['price_gross'], $row['unit'], $row['unit_label']], Setting::find('kiln_prices')->value));
        // The history learns the new prices, after the old ones it did not know yet.
        $this->assertSame(['kiln_prices:bisque_firing' => [4000, 3000], 'kiln_prices:glaze_firing' => [6000, 5000], 'kiln_prices:low_firing' => [4000]],
            OfferPriceHistory::query()->where('offer', '!=', 'kiln_prices:gold_firing')->orderBy('id')->get()->groupBy('offer')->sortKeys()
                ->map(fn ($rows) => $rows->pluck('price_gross')->all())->all());

        $this->assertSame([
            'Szamot i porcelana. Nierówne krawędzie zostawiam, bo tak wygląda ręka.',
            null,
            'Można myć w zmywarce. Złocone rzeczy tylko ręcznie.',
            'Można używać w mikrofalówce, poza rzeczami ze złotem.',
            null,
            'Zamówienia są dobrze zabezpieczone przed wysyłką. Wysyłam InPostem, z numerem do śledzenia.',
        ], array_column(Setting::find('material_tiles')->value, 'text'));
        $this->assertStringContainsString('Kubek z Twoim napisem to 4–8 tygodni, duże i skomplikowane projekty 6–12 tygodni', Setting::find('faq_items')->value[0]['answer']);
        $this->assertSame('gotowe w 4–8 tygodni', Setting::find('text_mug_lead_time')->value);
        $workshop = Setting::find('workshop_types')->value[0];
        $this->assertSame(['Lepienie kubków', '2 godziny', 22000], [$workshop['name'], $workshop['duration_label'], $workshop['price_gross']]);
        $this->assertStringStartsWith('Dwie godziny', Setting::find('voucher_workshop_notes')->value['voucher-lepienie']['expect']);
        $this->assertSame(15, Setting::find('euro_markup_percent')->value);
    }

    public function test_what_kasia_changed_in_the_panel_stays_and_a_second_run_changes_nothing(): void
    {
        $this->seed(SettingsSeeder::class);
        $kiln = [
            ['code' => 'bisque_firing', 'label' => 'Biskwit', 'note' => null, 'price_gross' => 4500, 'unit' => 'litre', 'unit_label' => '/ l'],
            ['code' => 'glaze_firing', 'label' => 'Wypał na ostro do 1240°C', 'note' => 'prace poszkliwione przez Ciebie', 'price_gross' => 6000, 'unit' => 'litre', 'unit_label' => '/ l'],
        ];
        $this->settings(['kiln_prices' => $kiln, 'text_mug_lead_time' => 'gotowe w 5 tygodni', 'euro_markup_percent' => 20]);
        $migration = require base_path(self::MIGRATION);

        $migration->up();
        $afterFirst = Setting::find('kiln_prices')->value;
        $migration->up();

        // The row she renamed stays by the litre; the one still as seeded goes by the kilogram, without a low firing.
        $this->assertSame([['Biskwit', 4500, 'litre'], ['Wypał wysokotemperaturowy do 1240°C', 5000, 'kg']],
            array_map(fn (array $row) => [$row['label'], $row['price_gross'], $row['unit']], $afterFirst));
        $this->assertSame($afterFirst, Setting::find('kiln_prices')->value);
        $this->assertSame('gotowe w 5 tygodni', Setting::find('text_mug_lead_time')->value);
        $this->assertSame(20, Setting::find('euro_markup_percent')->value);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
