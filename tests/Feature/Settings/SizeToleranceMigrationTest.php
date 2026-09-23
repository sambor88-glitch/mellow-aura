<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SizeToleranceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_database_seeded_before_the_setting_gets_kasias_half_a_centimetre(): void
    {
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_09_17_120000_add_size_tolerance_to_settings.php');

        $migration->up();
        $this->assertSame(0, Setting::count());

        $this->seed(SettingsSeeder::class);
        Setting::query()->whereKey('size_tolerance')->delete();
        $migration->up();
        $migration->up();
        $this->assertSame('0,5 cm', Setting::find('size_tolerance')->value);

        Setting::query()->whereKey('size_tolerance')->update(['value' => json_encode('1 cm')]);
        $migration->up();
        $this->assertSame('1 cm', Setting::find('size_tolerance')->value);
    }
}
