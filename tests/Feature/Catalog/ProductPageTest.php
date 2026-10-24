<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([SettingsSeeder::class, CatalogSeeder::class]);
    }

    public function test_product_page_with_title_description_and_variants(): void
    {
        $this->get('/produkt/patery')
            ->assertOk()
            ->assertSee('<title>Patery — ceramika handmade | MellowAura</title>', false)
            ->assertSee('<meta name="description" content="Duże formy dekoracyjne', false)
            ->assertSee('<h1 class="ma-h1 ma-h1--product">Patery</h1>', false)
            ->assertSee('Średnia 30 cm')
            ->assertSee('369,00')
            ->assertSee('Zmywarka tak, złoto tylko ręcznie')
            ->assertSee('Z tej samej półki');
    }

    public function test_silk_and_voucher_titles_follow_the_spec(): void
    {
        $this->get('/produkt/jedwabne-opaski')->assertSee('<title>Jedwabne opaski — rękodzieło z jedwabiu | MellowAura</title>', false);
        $this->get('/produkt/voucher-kwotowy')->assertSee('<title>Voucher kwotowy | MellowAura</title>', false);
    }

    public function test_product_group_markup_with_an_offer_per_variant(): void
    {
        $response = $this->get('/produkt/patery')->assertOk();

        $response->assertSee('"@type":"ProductGroup"', false);
        $response->assertSee('"price":"329.00"', false);
        $response->assertSee('"price":"399.00"', false);
        $response->assertSee('"availability":"https://schema.org/InStock"', false);
        $response->assertSee('"priceCurrency":"PLN"', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_draft_is_not_found(): void
    {
        $this->get('/produkt/miski')->assertNotFound();
    }

    public function test_sold_out_one_off_stays_with_200_and_out_of_stock(): void
    {
        Product::query()->where('slug', 'kadzielnice')->first()->variants()->update(['stock' => 0]);

        $this->get('/produkt/kadzielnice')
            ->assertOk()
            ->assertSee('"availability":"https://schema.org/OutOfStock"', false)
            ->assertSee('Ta sztuka już pojechała do kogoś');
    }

    public function test_crossed_out_price_comes_with_the_lowest_price_from_30_days(): void
    {
        $this->travelTo(now()->subDays(40));
        $variant = ProductVariant::factory()->create(['price_gross' => 12000, 'label' => 'Jedyny']);
        $this->travelBack();

        $this->travelTo(now()->subDays(15));
        $variant->update(['price_gross' => 9900]);
        $this->travelBack();

        $variant->update(['price_gross' => 8000, 'compare_at_price' => 12000]);

        $this->get(route('product.show', $variant->product))
            ->assertOk()
            ->assertSee('120,00')
            ->assertSee('Najniższa cena z 30 dni przed obniżką')
            ->assertSee('99,00');
    }

    public function test_product_name_is_escaped(): void
    {
        $product = Product::factory()->create(['name' => '<script>alert(1)</script>']);
        ProductVariant::factory()->for($product)->create();

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
