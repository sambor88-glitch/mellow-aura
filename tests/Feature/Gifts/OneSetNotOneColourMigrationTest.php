<?php

namespace Tests\Feature\Gifts;

use App\Modules\Gifts\Models\Bundle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneSetNotOneColourMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrate(): void
    {
        $migration = require base_path('app/Modules/Gifts/Database/Migrations/2026_11_01_210000_drop_one_colour_from_the_mug_and_scrunchie_bundle.php');
        $migration->up();
    }

    public function test_the_seeded_set_is_one_set_not_one_colour(): void
    {
        $bundle = Bundle::factory()->create([
            'name' => 'Kubek i scrunchie w jednym odcieniu',
            'description' => 'Mój znak firmowy: glina i jedwab dobrane w jeden odcień. Kubek 300 ml i scrunchie w rozmiarze średnim, zapakowane razem.',
        ]);

        $this->migrate();

        $bundle->refresh();
        $this->assertSame('Kubek i scrunchie w jednym zestawie', $bundle->name);
        $this->assertSame('Mój znak firmowy: glina i jedwab z jednej pracowni. Kubek 300 ml i scrunchie w rozmiarze średnim, zapakowane razem.', $bundle->description);
    }

    public function test_a_name_and_description_kasia_rewrote_stay(): void
    {
        $bundle = Bundle::factory()->create(['name' => 'Kubek i jedwab', 'description' => 'Na poranną kawę.']);

        $this->migrate();

        $bundle->refresh();
        $this->assertSame('Kubek i jedwab', $bundle->name);
        $this->assertSame('Na poranną kawę.', $bundle->description);
    }
}
