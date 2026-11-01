<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundlesLeadMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrate(): void
    {
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_11_01_210000_drop_matching_glaze_from_the_bundles_lead.php');
        $migration->up();
        app(Settings::class)->refresh();
    }

    public function test_the_seeded_lead_stops_promising_a_matching_glaze(): void
    {
        Setting::create(['key' => 'text_bundles_lead', 'value' => 'Zestawy, których nie znajdziesz w innej pracowni — bo mało kto robi jednocześnie ceramikę i rękodzieło tekstylne. Dobieram odcień szkliwa do tkaniny, nie odwrotnie.']);

        $this->migrate();

        $this->assertSame('Zestawy, których nie znajdziesz w innej pracowni — bo mało kto robi jednocześnie ceramikę i rękodzieło tekstylne.', app(Settings::class)->get('text_bundles_lead'));
    }

    public function test_a_lead_kasia_rewrote_stays(): void
    {
        Setting::create(['key' => 'text_bundles_lead', 'value' => 'Prezenty z mojej pracowni.']);

        $this->migrate();

        $this->assertSame('Prezenty z mojej pracowni.', app(Settings::class)->get('text_bundles_lead'));
    }
}
