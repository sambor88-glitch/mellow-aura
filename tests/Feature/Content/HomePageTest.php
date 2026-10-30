<?php

namespace Tests\Feature\Content;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Setting::create(['key' => 'dispatch_days_min', 'value' => 2]);
        Setting::create(['key' => 'dispatch_days_max', 'value' => 4]);
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
            // The hero piece is the one tile among the facts that leads somewhere, and says so.
            ->assertSeeInOrder(['href="'.route('product.show', 'patery').'"', 'Zobacz <span aria-hidden="true">→</span>'], false)
            ->assertSee('nowość')
            ->assertSeeText('domowa pracownia · kraków')
            ->assertDontSeeText('home studio')
            ->assertSeeTextInOrder(['Co teraz jest w pracowni', 'Wazony', 'Patery', 'Talerze', 'Kadzielnice'])
            ->assertDontSee('Scrunchies')
            ->assertSee('Lubię łączyć surowość z miękkością.')
            ->assertSeeInOrder(['2–4 dni', 'wysyłka zamówienia']);
    }

    public function test_the_first_screen_leads_to_the_shop_and_the_shelf_comes_before_kasia(): void
    {
        Setting::create(['key' => 'text_home_kasia_heading', 'value' => 'Lubię łączyć surowość z miękkością.']);
        $product = Product::factory()->create(['name' => 'Wazony', 'category_id' => Category::factory()->create()->id]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 2]);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([
                'data-hero-meta',
                'Ceramika i jedwab z jednej pary rąk, z krakowskiej pracowni',
                'href="'.route('shop.index').'"',
                'Zobacz sklep',
                'id="hero-title"',
            ], false)
            ->assertSeeTextInOrder(['Co teraz jest w pracowni', 'Wazony', 'Cześć, jestem Kasia', 'Lubię łączyć surowość z miękkością.']);

        $this->get('/en')
            ->assertOk()
            ->assertSee('Ceramics and silk from one pair of hands, from a studio in Kraków')
            ->assertSee('href="'.url('/en/shop').'"', false);
    }

    public function test_the_workshops_section_shows_the_prices_and_leads_to_their_page(): void
    {
        Setting::create(['key' => 'workshop_types', 'value' => [
            ['name' => 'Lepienie z ręki', 'duration_label' => '2,5 godziny', 'summary' => 'Pierwszy raz w glinie.', 'price_gross' => 22000, 'unit_label' => 'os.'],
        ]]);

        $this->get('/')
            ->assertOk()
            ->assertSeeText('Zanurz dłonie w glinie')
            ->assertSee('od 220 zł / os.')
            ->assertSee('href="'.route('workshops.index').'"', false)
            // A row leads to its own card, the button to the dates — not to the top of the page.
            ->assertSee('href="'.route('workshops.index').'#workshop-lepienie_z_reki"', false)
            ->assertSee('href="'.route('workshops.index').'#workshop-dates"', false);

        $this->get('/warsztaty-ceramiczne-krakow')->assertSee('id="workshop-lepienie_z_reki"', false);
    }

    public function test_an_empty_shop_does_not_break_the_home_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSeeText('Co teraz jest w pracowni')
            // Without days to dispatch in the panel the home page promises none.
            ->assertDontSee('wysyłka zamówienia');
    }
}
