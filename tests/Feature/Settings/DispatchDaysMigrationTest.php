<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchDaysMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_database_seeded_before_the_setting_keeps_the_3_to_5_days(): void
    {
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_09_17_100000_add_dispatch_days_to_settings.php');

        // An empty database is left to the seeder.
        $migration->up();
        $this->assertSame(0, Setting::count());

        $this->seed(SettingsSeeder::class);
        Setting::query()->whereIn('key', ['dispatch_days_min', 'dispatch_days_max'])->delete();

        $migration->up();
        $migration->up();

        $this->assertSame([3, 5], [Setting::find('dispatch_days_min')->value, Setting::find('dispatch_days_max')->value]);
        $this->assertSame(1, Setting::query()->where('key', 'dispatch_days_min')->count());
    }

    public function test_days_saved_in_the_panel_stay(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::query()->where('key', 'dispatch_days_min')->update(['value' => json_encode(2)]);
        Setting::query()->where('key', 'dispatch_days_max')->delete();

        (require base_path('app/Modules/Settings/Database/Migrations/2026_09_17_100000_add_dispatch_days_to_settings.php'))->up();

        $this->assertSame([2, 5], [Setting::find('dispatch_days_min')->value, Setting::find('dispatch_days_max')->value]);
    }
}
