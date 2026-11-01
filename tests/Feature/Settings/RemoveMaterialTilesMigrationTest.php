<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MA-353: the material tiles left „O mnie” and the panel, so the migration removes the saved ones and nothing else.
 */
class RemoveMaterialTilesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_saved_tiles_are_removed_and_the_other_settings_stay(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::query()->updateOrCreate(['key' => 'material_tiles'], ['value' => [['title' => 'Glina', 'text' => 'Opis gliny.']]]);
        $others = Setting::query()->where('key', '!=', 'material_tiles')->count();

        (require base_path('app/Modules/Settings/Database/Migrations/2026_11_01_210000_remove_the_material_tiles_setting.php'))->up();

        $this->assertNull(Setting::find('material_tiles'));
        $this->assertSame($others, Setting::query()->count());
    }

    public function test_a_fresh_seed_has_no_tiles(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->assertNull(Setting::find('material_tiles'));
    }
}
