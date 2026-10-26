<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchersWordsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_IMPRINT = 'Przyślij zasuszoną roślinę — kwiat z bukietu ślubnego, gałązkę z wieńca, listek z pierwszego wspólnego mieszkania. Odciskam ją w surowej glinie i wypalam.';

    public function test_a_seeded_lead_gets_the_searchers_words_and_one_kasia_changed_stays(): void
    {
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_10_26_070000_speak_searchers_words_in_service_leads.php');

        $migration->up();
        $this->assertSame(0, Setting::count());

        $this->seed(SettingsSeeder::class);
        Setting::query()->whereKey('text_imprint_lead')->update(['value' => json_encode(self::OLD_IMPRINT)]);
        Setting::query()->whereKey('text_scarf_lead')->update(['value' => json_encode('Mój własny akapit.')]);
        $customLead = Setting::find('text_custom_orders_lead')->value;

        $migration->up();
        $migration->up();

        $this->assertStringStartsWith('Nie wiesz, co zrobić z bukietem po weselu?', Setting::find('text_imprint_lead')->value);
        $this->assertSame('Mój własny akapit.', Setting::find('text_scarf_lead')->value);
        $this->assertSame($customLead, Setting::find('text_custom_orders_lead')->value);
        $this->assertStringContainsString('urna dla kota', $customLead);

        $migration->down();
        $this->assertSame(self::OLD_IMPRINT, Setting::find('text_imprint_lead')->value);
        $this->assertStringNotContainsString('urna dla kota', Setting::find('text_custom_orders_lead')->value);
    }
}
