<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioFactsHeadingMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrate(): void
    {
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_11_01_220000_add_studio_facts_heading_to_settings.php');
        $migration->up();
        app(Settings::class)->refresh();
    }

    public function test_the_heading_starts_with_what_the_page_said(): void
    {
        Setting::create(['key' => 'studio_facts', 'value' => [['title' => 'Do 6 osób', 'text' => null]]]);

        $this->migrate();

        $this->assertSame('Dobrze wiedzieć', app(Settings::class)->get('studio_facts_heading'));
    }

    public function test_a_heading_kasia_changed_or_emptied_stays(): void
    {
        Setting::create(['key' => 'studio_facts_heading', 'value' => null]);

        $this->migrate();

        $this->assertNull(app(Settings::class)->get('studio_facts_heading'));
    }

    public function test_an_empty_database_is_left_to_the_seeder(): void
    {
        $this->migrate();

        $this->assertNull(Setting::find('studio_facts_heading'));
    }
}
