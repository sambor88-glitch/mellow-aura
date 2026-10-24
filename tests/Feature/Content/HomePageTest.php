<?php

namespace Tests\Feature\Content;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Settings\Actions\UpdateSetting;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([SettingsSeeder::class, CatalogSeeder::class]);
    }

    public function test_home_page_from_the_prototype(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>MellowAura — ceramika i rękodzieło z jedwabiu, Kraków</title>', false)
            ->assertSee('z <em>jednej pary</em> rąk.', false)
            ->assertSee('Co teraz jest w pracowni')
            ->assertSee('nowość')
            ->assertSee('Lubię łączyć surowość z miękkością');
    }

    public function test_calls_to_action_wait_for_their_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Zobacz produkty')
            ->assertDontSee('Zaprojektuj kubek z napisem')
            ->assertDontSee('Poznaj mnie bliżej');
    }

    public function test_hero_falls_back_to_the_first_product_when_the_chosen_one_is_missing(): void
    {
        app(UpdateSetting::class)->handle('home.hero_product', 'does-not-exist');

        $this->get('/')->assertOk()->assertSee('ma-hero__tag', false);
    }
}
