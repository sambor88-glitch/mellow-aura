<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_settings_from_the_prototype(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->assertSame(40000, Setting::find('free_shipping_threshold')->value);
        $this->assertSame('Kraków, okolice Błoń Krakowskich', Setting::find('location_description')->value);
        $this->assertFalse(Setting::find('workshop_deposit_enabled')->value);
        $this->assertIsArray(Setting::find('shipping_methods')->value);
        $this->assertNull(Setting::find('contact_email')->value);
    }

    public function test_running_it_again_keeps_values_changed_in_the_panel(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::find('free_shipping_threshold')->update(['value' => 30000]);

        $this->seed(SettingsSeeder::class);

        $this->assertSame(30000, Setting::find('free_shipping_threshold')->value);
    }
}
