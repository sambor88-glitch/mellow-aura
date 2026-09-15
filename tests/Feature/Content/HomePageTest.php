<?php

namespace Tests\Feature\Content;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_home_page_shows_the_hero_product_and_what_is_on_the_shelf(): void
    {
        Setting::create(['key' => 'home_hero_product', 'value' => 'patery']);
        Setting::create(['key' => 'home_hero_badge', 'value' => 'nowość']);
        Setting::create(['key' => 'text_home_kasia_heading', 'value' => 'Lubię łączyć surowość z miękkością.']);
        $category = Category::factory()->create();

        foreach (['Wazony', 'Patery', 'Talerze', 'Kadzielnice', 'Scrunchies'] as $position => $name) {
            $product = Product::factory()->create([
                'name' => $name,
                'slug' => str($name)->slug()->toString(),
                'category_id' => $category->id,
                'sort_order' => $position + 1,
            ]);
            ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 2]);
        }

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>MellowAura — ceramika i rękodzieło z jedwabiu, Kraków</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
            ->assertSee('href="'.route('product.show', 'patery').'"', false)
            ->assertSee('nowość')
            ->assertSeeInOrder(['Co teraz jest w pracowni', 'Wazony', 'Patery', 'Talerze', 'Kadzielnice'])
            ->assertDontSee('Scrunchies')
            ->assertSee('Lubię łączyć surowość z miękkością.');
    }

    public function test_sections_leading_to_missing_pages_appear_once_the_page_has_a_route(): void
    {
        Setting::create(['key' => 'workshop_types', 'value' => [
            ['name' => 'Lepienie z ręki', 'duration_label' => '2,5 godziny', 'summary' => 'Pierwszy raz w glinie.', 'price_gross' => 22000, 'unit_label' => 'os.'],
        ]]);

        $this->get('/')->assertOk()->assertDontSee('Zanurz dłonie w glinie');

        Route::get('/warsztaty-ceramiczne-krakow', fn () => '')->name('workshops.index');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/')
            ->assertOk()
            ->assertSee('Zanurz dłonie w glinie')
            ->assertSee('od 220,00 zł / os.')
            ->assertSee('href="'.url('/warsztaty-ceramiczne-krakow').'"', false);
    }

    public function test_an_empty_shop_does_not_break_the_home_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Co teraz jest w pracowni');
    }
}
