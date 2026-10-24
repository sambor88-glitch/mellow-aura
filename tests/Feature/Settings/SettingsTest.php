<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Actions\UpdateSetting;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_seeded_values(): void
    {
        $this->seed(SettingsSeeder::class);

        $settings = app(Settings::class);

        $this->assertSame(40000, $settings->get('shipping.free_threshold'));
        $this->assertSame('Kraków, okolice Błoń Krakowskich', $settings->get('location.description'));
    }

    public function test_empty_value_falls_back_to_default(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->assertNull(app(Settings::class)->get('contact.email'));
        $this->assertSame('fallback', app(Settings::class)->get('missing.key', 'fallback'));
    }

    public function test_update_is_visible_immediately(): void
    {
        $this->seed(SettingsSeeder::class);
        $settings = app(Settings::class);
        $settings->get('shipping.free_threshold');

        app(UpdateSetting::class)->handle('shipping.free_threshold', 35000);

        $this->assertSame(35000, $settings->get('shipping.free_threshold'));
    }

    public function test_seeder_keeps_values_changed_in_panel(): void
    {
        $this->seed(SettingsSeeder::class);
        app(UpdateSetting::class)->handle('contact.email', 'kasia@example.com');

        $this->seed(SettingsSeeder::class);

        $this->assertSame('kasia@example.com', Setting::query()->find('contact.email')->value);
    }
}
