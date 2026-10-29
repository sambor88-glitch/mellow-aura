<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioTextsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_LEAD = 'Warsztaty, które uczą nie tylko ceramiki – ale też uważności i słuchania siebie. Zanurz dłonie w glinie – poczuj spokój, radość i twórczy flow.';

    private const OLD_ADDRESS = 'Dokładny adres podaję po zapisaniu się — to home studio, więc wpuszczam po jednej grupie naraz. Na miejscu jest herbata, fartuchy i muzyka, którą możesz zmienić.';

    private function migrate(): void
    {
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_10_29_220000_put_the_studio_texts_in_polish.php');
        $migration->up();
        app(Settings::class)->refresh();
    }

    public function test_the_seeded_texts_turn_polish(): void
    {
        Setting::query()->whereIn('key', ['text_studio_lead', 'text_studio_paragraph_2'])->delete();
        Setting::create(['key' => 'text_studio_lead', 'value' => self::OLD_LEAD]);
        Setting::create(['key' => 'text_studio_paragraph_2', 'value' => self::OLD_ADDRESS]);

        $this->migrate();

        $settings = app(Settings::class);
        $this->assertSame('Warsztaty, które uczą nie tylko ceramiki — ale też uważności i słuchania siebie. Zanurz dłonie w glinie — poczuj spokój, radość i twórczą swobodę.', $settings->get('text_studio_lead'));
        $this->assertSame('Dokładny adres podaję po zapisaniu się — to domowa pracownia, więc wpuszczam po jednej grupie naraz. Na miejscu jest herbata, fartuchy i muzyka, którą możesz zmienić.', $settings->get('text_studio_paragraph_2'));
    }

    public function test_a_text_kasia_rewrote_stays(): void
    {
        Setting::query()->where('key', 'text_studio_lead')->delete();
        Setting::create(['key' => 'text_studio_lead', 'value' => 'Warsztaty dla każdej – bez presji.']);

        $this->migrate();

        $this->assertSame('Warsztaty dla każdej – bez presji.', app(Settings::class)->get('text_studio_lead'));
    }

    public function test_the_seeder_has_no_english_words_or_short_dashes_between_words(): void
    {
        $seeded = file_get_contents(base_path('app/Modules/Settings/Database/Seeders/settings.json'));

        $this->assertStringNotContainsString('home studio', $seeded);
        $this->assertStringNotContainsString('twórczy flow', $seeded);
        $this->assertStringNotContainsString(' – ', $seeded);
    }
}
