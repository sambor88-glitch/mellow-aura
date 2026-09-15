<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_product_page_shows_the_offer_for_the_chosen_variant(): void
    {
        Setting::create(['key' => 'care_rule_ceramics', 'value' => 'Zmywarka tak, złoto tylko ręcznie']);
        Setting::create(['key' => 'free_shipping_threshold', 'value' => 40000]);
        $vases = Category::factory()->create(['name' => 'Wazony i patery', 'group' => CategoryGroup::Ceramics]);
        $product = Product::factory()->create([
            'slug' => 'wazony',
            'name' => 'Wazony',
            'category_id' => $vases->id,
            'description' => 'Wazon lepiony z ręki.',
            'dimensions' => ['height_cm' => '24', 'thickness_mm' => '6'],
        ]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => 3]);
        $tall = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Wysoki 24 cm', 'price_gross' => 29900, 'stock' => 1]);

        $this->get('/produkt/wazony')
            ->assertOk()
            ->assertSee('<title>Wazony — ceramika handmade | MellowAura</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/produkt/wazony').'">', false)
            ->assertSee('Wazon lepiony z ręki.')
            ->assertSee('239,00 zł')
            ->assertSee(['Wysokość', '24 cm', 'Grubość', '6 mm'])
            ->assertSee('Zmywarka tak, złoto tylko ręcznie')
            ->assertSee('gratis od 400,00 zł')
            ->assertDontSee('ostatnia sztuka');

        $this->get('/produkt/wazony?wariant='.$tall->id)
            ->assertOk()
            ->assertSee('ostatnia sztuka — kolejną zrobię na zamówienie');
    }

    public function test_the_page_describes_every_variant_for_search_engines(): void
    {
        $mugs = Category::factory()->create(['name' => 'Kubki i filiżanki', 'slug' => 'kubki-i-filizanki']);
        $product = Product::factory()->create(['slug' => 'kubki-z-cytatem', 'name' => 'Kubki z cytatem', 'category_id' => $mugs->id]);
        $sold = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Królowa matka', 'price_gross' => 7900, 'stock' => 0]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Twój tekst', 'price_gross' => 7900, 'stock' => null]);

        $html = $this->get('/produkt/kubki-z-cytatem')->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $blocks = collect($matches[1])->map(fn (string $json) => json_decode($json, true));
        $group = $blocks->firstWhere('@type', 'ProductGroup');
        $breadcrumbs = $blocks->firstWhere('@type', 'BreadcrumbList');

        $this->assertSame('kubki-z-cytatem', $group['productGroupID']);
        $this->assertCount(2, $group['hasVariant']);
        $this->assertSame('79.00', $group['hasVariant'][0]['offers']['price']);
        $this->assertSame('https://schema.org/OutOfStock', $group['hasVariant'][0]['offers']['availability']);
        $this->assertSame('https://schema.org/InStock', $group['hasVariant'][1]['offers']['availability']);
        $this->assertSame(url('/produkt/kubki-z-cytatem').'?wariant='.$sold->id, $group['hasVariant'][0]['offers']['url']);
        $this->assertSame(['Sklep', 'Kubki i filiżanki', 'Kubki z cytatem'], array_column($breadcrumbs['itemListElement'], 'name'));
    }

    public function test_drafts_and_unknown_products_are_not_found(): void
    {
        $draft = Product::factory()->create(['slug' => 'miski', 'is_published' => false]);
        ProductVariant::factory()->create(['product_id' => $draft->id]);

        $this->get('/produkt/miski')->assertNotFound();
        $this->get('/produkt/nie-ma-takiego')->assertNotFound();
    }

    public function test_a_discount_shows_the_lowest_price_from_the_30_days_before_it(): void
    {
        $product = Product::factory()->create(['slug' => 'talerze']);

        $this->travelTo(now()->subDays(45));
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => 15900]);
        $this->travelBack();

        $this->travelTo(now()->subDays(20));
        $variant->update(['price_gross' => 14500]);
        $this->travelBack();

        $this->travelTo(now()->subDays(5));
        $variant->update(['price_gross' => 12900, 'compare_at_price' => 15900]);
        $this->travelBack();

        $this->assertSame(14500, $variant->fresh()->lowestPriceBeforeDiscount());

        $this->get('/produkt/talerze')
            ->assertOk()
            ->assertSee('129,00 zł')
            ->assertSee('159,00 zł')
            ->assertSee('Najniższa cena z 30 dni przed obniżką: 145,00 zł');
    }

    public function test_a_discount_without_an_earlier_price_is_not_shown(): void
    {
        $product = Product::factory()->create(['slug' => 'kubki']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => 7900, 'compare_at_price' => 9900]);

        $this->get('/produkt/kubki')
            ->assertOk()
            ->assertDontSee('99,00 zł')
            ->assertDontSee('Najniższa cena');
    }

    public function test_related_products_come_from_the_same_category_first_then_the_same_group(): void
    {
        $plates = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $vases = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $silk = Category::factory()->create(['group' => CategoryGroup::Crafts]);

        $this->shelf('Talerz obiadowy', $vases, 1);
        $this->shelf('Opaska jedwabna', $silk, 2);
        $this->shelf('Talerz deserowy', $plates, 3);
        $this->shelf('Talerz serwisowy', $plates, 4);

        $this->get('/produkt/talerz-serwisowy')
            ->assertOk()
            ->assertSeeInOrder(['Z tej samej półki', 'Talerz deserowy', 'Talerz obiadowy'])
            ->assertDontSee('Opaska jedwabna');
    }

    private function shelf(string $name, Category $category, int $sortOrder): Product
    {
        $product = Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'category_id' => $category->id,
            'sort_order' => $sortOrder,
            'description' => null,
        ]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 2]);

        return $product;
    }
}
