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
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
            ->assertSee('z <em>jednej pary</em> rąk.', false)
            ->assertSee('Co teraz jest w pracowni')
            ->assertSee('nowość')
            ->assertSee('Lubię łączyć surowość z miękkością');
    }

    public function test_links_only_to_pages_that_exist(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('href="'.route('shop.index').'"', false);
        $response->assertDontSee('/warsztaty-ceramiczne-krakow');
        $response->assertDontSee('/kubek-z-napisem');
    }

    public function test_organization_markup_without_street_address(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('"@type":["Organization","LocalBusiness"]', false);
        $response->assertSee('"addressLocality":"Kraków"', false);
        $response->assertSee('"priceRange":"59–600 PLN"', false);
        $response->assertDontSee('streetAddress');
    }

    public function test_hero_falls_back_to_the_first_product_when_the_chosen_one_is_missing(): void
    {
        app(UpdateSetting::class)->handle('home.hero_product', 'does-not-exist');

        $this->get('/')->assertOk()->assertSee('ma-hero__tag', false);
    }
}
