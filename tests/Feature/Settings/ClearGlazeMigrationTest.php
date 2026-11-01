<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearGlazeMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrate(): void
    {
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_11_01_220000_add_a_clear_glaze_to_the_mug.php');
        $migration->up();
    }

    public function test_the_clear_glaze_joins_the_end_of_the_list(): void
    {
        Setting::create(['key' => 'mug_glazes', 'value' => [['code' => 'turquoise', 'name' => 'Turkus', 'hex' => '#2E7A85']]]);

        $this->migrate();

        $this->assertEquals([
            ['code' => 'turquoise', 'name' => 'Turkus', 'hex' => '#2E7A85'],
            ['code' => 'transparent', 'name' => 'Transparentne', 'hex' => '#D9C4A8'],
        ], Setting::find('mug_glazes')->value);
    }

    public function test_a_list_that_has_it_stays_as_it_is(): void
    {
        $glazes = [['code' => 'transparent', 'name' => 'Bezbarwne', 'hex' => '#E0CFB6'], ['code' => 'cobalt', 'name' => 'Kobalt', 'hex' => '#24417E']];
        Setting::create(['key' => 'mug_glazes', 'value' => $glazes]);

        $this->migrate();

        $this->assertEquals($glazes, Setting::find('mug_glazes')->value);
    }

    public function test_without_the_setting_nothing_is_added(): void
    {
        $this->migrate();

        $this->assertNull(Setting::find('mug_glazes'));
    }
}
