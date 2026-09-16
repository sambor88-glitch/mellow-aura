<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_shop_lists_published_products_that_are_on_the_shelf(): void
    {
        $mugs = Category::factory()->create(['name' => 'Kubki i filiżanki', 'slug' => 'kubki-i-filizanki']);
        $painted = $this->product('Kubki malowane ręcznie', $mugs, [6900, 7900]);
        $this->product('Voucher kwotowy', $mugs, [15000], stock: null);
        $this->product('Szkic miski', $mugs, [8900], ['is_published' => false]);
        $this->product('Wazon sprzedany', $mugs, [29900], stock: 0);

        $this->get('/sklep')
            ->assertOk()
            ->assertSee('<title>Sklep — ceramika i rękodzieło handmade | MellowAura</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/sklep').'">', false)
            ->assertSee('Kubki malowane ręcznie')
            ->assertSee('href="'.route('product.show', $painted).'"', false)
            ->assertSee('od 69,00 zł')
            ->assertSee('2 warianty')
            ->assertSee('Voucher kwotowy')
            ->assertSee('150,00 zł')
            ->assertDontSee('Szkic miski')
            ->assertDontSee('Wazon sprzedany')
            ->assertSee('2 z 2 produktów na półce');
    }

    public function test_a_category_page_shows_only_its_products(): void
    {
        $mugs = Category::factory()->create(['name' => 'Kubki i filiżanki', 'slug' => 'kubki-i-filizanki']);
        $silk = Category::factory()->create(['name' => 'Jedwab', 'slug' => 'jedwab']);
        $this->product('Kubki malowane ręcznie', $mugs, [7900]);
        $this->product('Jedwabne opaski', $silk, [8900]);

        Setting::create(['key' => 'dispatch_days_min', 'value' => 3]);
        Setting::create(['key' => 'dispatch_days_max', 'value' => 5]);

        $this->get('/sklep')->assertSee('<meta name="description" content="Kubki, talerze, wazony, patery, kadzielnice oraz jedwabne scrunchies i opaski. Każda sztuka jedna, wysyłka w 3–5 dni, BLIK.">', false);

        // Without a description from the panel each category names what is in it.
        $this->get('/sklep/jedwab')
            ->assertOk()
            ->assertSee('<meta name="description" content="Jedwab z pracowni w Krakowie: jedwabne opaski. Ręczna robota, wysyłka w 3–5 dni.">', false)
            ->assertSee('<title>Jedwab — rękodzieło z Krakowa | MellowAura</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/sklep/jedwab').'">', false)
            ->assertSee('Jedwabne opaski')
            ->assertDontSee('Kubki malowane ręcznie');

        $this->get('/sklep/nie-ma-takiej')->assertNotFound();
    }

    public function test_a_category_page_links_its_breadcrumbs_and_describes_them_for_search_engines(): void
    {
        $silk = Category::factory()->create(['name' => 'Jedwab', 'slug' => 'jedwab']);
        $this->product('Jedwabne opaski', $silk, [8900]);

        $response = $this->get('/sklep/jedwab')
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Okruszki"', 'href="'.url('/').'"', 'Strona główna', 'href="'.url('/sklep').'"', 'Produkty', 'Jedwab'], false);

        $breadcrumbs = $this->structuredData($response->getContent())->firstWhere('@type', 'BreadcrumbList');

        $this->assertSame(['Strona główna', 'Produkty', 'Jedwab'], array_column($breadcrumbs['itemListElement'], 'name'));
        $this->assertSame(url('/sklep/jedwab'), $breadcrumbs['itemListElement'][2]['item']);
    }

    public function test_the_studio_markup_takes_its_price_range_from_what_is_on_the_shelf(): void
    {
        $category = Category::factory()->create();
        $this->product('Scrunchies', $category, [5900]);
        $this->product('Patery', $category, [32900, 49900]);
        $this->product('Szkic miski', $category, [2900], ['is_published' => false]);
        $this->product('Wazon sprzedany', $category, [89900], stock: 0);

        $business = $this->structuredData($this->get('/sklep')->getContent())->firstWhere('@type', 'LocalBusiness');

        $this->assertSame('59–499 zł', $business['priceRange']);
    }

    public function test_search_filters_products_and_is_kept_out_of_the_index(): void
    {
        $category = Category::factory()->create();
        $this->product('Kubki malowane ręcznie', $category, [7900]);
        $this->product('Jedwabne opaski', $category, [8900]);

        $this->get('/sklep?q=OPASK')
            ->assertOk()
            ->assertSee('Jedwabne opaski')
            ->assertDontSee('Kubki malowane ręcznie')
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('<link rel="canonical" href="'.url('/sklep').'">', false);
    }

    public function test_products_can_be_sorted_by_their_lowest_price(): void
    {
        $category = Category::factory()->create();
        $this->product('Patery', $category, [32900, 39900]);
        $this->product('Scrunchies', $category, [5900, 7900]);

        $this->get('/sklep?sort=price_asc')->assertSeeInOrder(['Scrunchies', 'Patery']);
        $this->get('/sklep?sort=price_desc')->assertSeeInOrder(['Patery', 'Scrunchies']);
    }

    public function test_the_footer_shows_settings_and_skips_empty_ones(): void
    {
        Setting::create(['key' => 'footer_city', 'value' => 'Kraków, Polska']);
        Setting::create(['key' => 'instagram_handle', 'value' => null]);

        $this->get('/sklep')
            ->assertSee('Kraków, Polska')
            ->assertDontSee('Instagram');
    }

    /**
     * @param  list<int>  $prices
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, Category $category, array $prices, array $attributes = [], ?int $stock = 3): Product
    {
        $product = Product::factory()->create(['name' => $name, 'category_id' => $category->id, 'description' => null, ...$attributes]);

        foreach ($prices as $price) {
            ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => $price, 'stock' => $stock]);
        }

        return $product;
    }
}
