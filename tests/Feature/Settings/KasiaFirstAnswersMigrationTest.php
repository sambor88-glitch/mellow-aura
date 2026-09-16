<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasiaFirstAnswersMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_sentences_get_kasias_answers_and_rewritten_ones_stay(): void
    {
        $this->seed(SettingsSeeder::class);
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_09_16_180000_apply_kasias_first_answers_to_settings.php');

        $this->settings([
            'material_tiles' => [
                ['title' => 'Glina', 'text' => 'Kamionka i szamot piaskowy. Nierówne krawędzie zostawiam, bo tak wygląda ręka.'],
                ['title' => 'Jedwab', 'text' => 'Mój własny opis jedwabiu.'],
            ],
            'studio_facts' => [['title' => 'Od 6 lat', 'text' => 'Dzieci z osobą dorosłą.'], ['title' => 'Dwa wypały w cenie', 'text' => 'Prace do odbioru po ~3 tygodniach.']],
            'workshop_types' => [
                ['name' => 'Szkliwienie i malowanie', 'group_label' => 'grupa do 8 osób', 'includes' => ['Wypał na ostro', 'Odbiór po 3 tygodniach']],
                ['name' => 'Rodzinnie z dzieckiem', 'group_label' => 'od 6 lat', 'summary' => 'Krótko, konkretnie i bez presji — tyle, ile wytrzyma uwaga sześciolatka.'],
            ],
            'text_mug_lead_time' => 'gotowe w 3 tygodnie',
            'text_kiln_note' => 'Wsad zbieram raz w tygodniu, zwykle w czwartek. Prace odbierzesz po 5–7 dniach. Nie przyjmuję gliny nieznanego pochodzenia ani prac grubszych niż 2 cm w ściance.',
        ]);

        $migration->up();

        $this->assertSame(['Nierówne krawędzie zostawiam, bo tak wygląda ręka.', 'Mój własny opis jedwabiu.'], array_column(Setting::find('material_tiles')->value, 'text'));
        $this->assertSame(['Od 5 lat', 'Dwa wypały w cenie'], array_column(Setting::find('studio_facts')->value, 'title'));
        $this->assertSame('Prace do odbioru po ~4 tygodniach.', Setting::find('studio_facts')->value[1]['text']);
        [$glazing, $family] = Setting::find('workshop_types')->value;
        $this->assertSame(['grupa do 6 osób', ['Wypał na ostro', 'Odbiór po 4 tygodniach']], [$glazing['group_label'], $glazing['includes']]);
        $this->assertSame('od 5 lat, do 3 par', $family['group_label']);
        $this->assertStringEndsWith('uwaga pięciolatka.', $family['summary']);
        $this->assertSame('gotowe w 4 tygodnie', Setting::find('text_mug_lead_time')->value);
        $this->assertNull(Setting::find('text_kiln_note')->value);

        // Running it again changes nothing, and a sentence written in the panel is kept.
        $this->settings(['text_mug_lead_time' => 'gotowe w 5 tygodni']);
        $migration->up();
        $this->assertSame('gotowe w 5 tygodni', Setting::find('text_mug_lead_time')->value);
    }

    public function test_fresh_seed_already_has_the_answers(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->get('/pracownia')->assertSee('Od 5 lat')->assertSee('Prace do odbioru po ~4 tygodniach.')->assertDontSee('Od 6 lat');
        $this->get('/o-mnie')->assertDontSee('Kamionka')->assertDontSee('końcówek bel');
        $this->get('/wysylka-i-pielegnacja')->assertSee('Zamówienia indywidualne to około 4 tygodnie')->assertDontSee('nic nie dotarło stłuczone');
        $this->get('/warsztaty-ceramiczne-krakow')->assertSee('grupa do 6 osób')->assertSee('od 5 lat, do 3 par')->assertDontSee('grupa do 8 osób');
        $this->get('/odcisk-twojej-rosliny')->assertSee('Cztery, pięć tygodni.');
        $this->get('/wypal-ceramiki-krakow')->assertDontSee('Wsad zbieram');
        $this->get('/regulamin')
            ->assertSee('Dzieci od 5 lat mogą')
            ->assertSee('do 48 godzin przed jego rozpoczęciem otrzymuje zwrot całej wpłaty')
            ->assertSee('najwyżej 6 osób, a w Warsztacie rodzinnym — 3 pary')
            ->assertSee('Rezerwacja jest potwierdzona, gdy Klient opłaci całą cenę Warsztatu.')
            ->assertDontSee('pytanie 1 ankiety');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->where('key', $key)->update(['value' => json_encode($value)]);
        }
    }
}
