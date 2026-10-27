<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipOnlyWithinPolandFaqMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'app/Modules/Settings/Database/Migrations/2026_10_27_130000_ship_only_within_poland_in_faq.php';

    public function test_the_seeded_answer_stops_promising_the_eu_and_a_rewritten_one_stays(): void
    {
        Setting::create(['key' => 'faq_items', 'value' => [
            ['question' => 'Wysyłasz za granicę?', 'answer' => 'Tak, do Unii Europejskiej. Napisz przed zamówieniem — policzę wysyłkę dla Twojego kraju i dobiorę opakowanie do dystansu.'],
            ['question' => 'A do Niemiec?', 'answer' => 'Kasia pisze to po swojemu: tak, do Unii Europejskiej.'],
        ]]);

        (require base_path(self::MIGRATION))->up();

        $this->assertSame([
            'Na razie wysyłam tylko w Polsce.',
            'Kasia pisze to po swojemu: tak, do Unii Europejskiej.',
        ], array_column(Setting::find('faq_items')->value, 'answer'));
    }
}
