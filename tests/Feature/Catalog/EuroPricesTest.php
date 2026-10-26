<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\PriceHistory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class EuroPricesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_euro_reads_the_english_way_on_an_english_page_and_the_polish_way_elsewhere(): void
    {
        App::setLocale('en');
        $this->assertSame(['€39.00', '239,00 zł'], [Money::format(3900, 'EUR'), Money::format(23900)]);

        App::setLocale('pl');
        $this->assertSame('39,50 €', Money::format(3950, 'EUR'));
    }

    public function test_a_variant_has_its_price_in_the_currency_of_the_page_and_none_without_a_euro_row(): void
    {
        [$mug, $small, $large] = $this->mug();

        App::setLocale('en');
        $this->assertSame([3900, null], [$small->price(), $large->price()]);

        App::setLocale('pl');
        $this->assertSame([14900, 17900], [$small->price(), $large->price()]);
        $this->assertSame(3900, $small->price('EUR'));
    }

    public function test_the_english_shop_shows_euro_and_only_what_has_a_euro_price(): void
    {
        [$mug] = $this->mug();
        $unpriced = $this->translatedProduct('Talerz', 'Plate', 8900);

        $this->get('/en/shop')
            ->assertOk()
            ->assertSee('Painted mug')
            // The large size has no euro price, so the card shows one price, not „from”.
            ->assertSee('€39.00')
            ->assertDontSee('from €')
            // Prices only; the studio's own price range for Google stays in złoty (a business in Kraków).
            ->assertDontSee(',00 zł')
            ->assertDontSee('Plate');

        $this->get('/sklep')
            ->assertSee('od 149,00 zł')
            ->assertSee('Talerz')
            ->assertDontSee('€');

        // Written in English but not priced: its English address leads to the English shop.
        $this->get('/en/product/plate')->assertRedirect('/en/shop');
        $this->get('/produkt/talerz')->assertDontSee('hreflang="en" href=', false);
    }

    public function test_the_english_product_page_prices_everything_in_euro(): void
    {
        [$mug, $small] = $this->mug();

        $response = $this->get('/en/product/painted-mug')
            ->assertOk()
            ->assertSee('€39.00')
            ->assertSee('x-text="$store.cart.format(3900 * quantity)"', false)
            ->assertSee('"priceCurrency":"EUR"', false)
            ->assertSee('"price":"39.00"', false)
            // Only the size priced in euro is on offer.
            ->assertDontSee('Large')
            ->assertDontSee(',00 zł')
            // Delivery and returns in the offer are the Polish ones, so the euro offer leaves them out for now.
            ->assertDontSee('shippingDetails', false);

        $this->assertStringNotContainsString('"priceCurrency":"PLN"', $response->getContent());

        $this->get('/produkt/malowany-kubek')
            ->assertSee('"priceCurrency":"PLN"', false)
            ->assertSee('149,00 zł');
    }

    public function test_the_lowest_price_before_a_reduction_is_counted_in_each_currency_on_its_own(): void
    {
        Date::setTestNow('2026-09-01 10:00');
        [$mug, $small] = $this->mug();
        $euro = $small->prices()->where('currency', 'EUR')->sole();

        // Złoty drops first, euro a week later from a different starting point.
        Date::setTestNow('2026-09-10 10:00');
        $small->update(['price_gross' => 12900, 'compare_at_price' => 14900]);
        Date::setTestNow('2026-09-17 10:00');
        $euro->update(['amount_minor' => 3500, 'compare_at_minor' => 3900]);

        $this->assertSame(['PLN', 'EUR', 'PLN', 'EUR'], PriceHistory::query()->where('product_variant_id', $small->id)->orderBy('id')->pluck('currency')->all());
        $this->assertSame(14900, $small->fresh()->lowestPriceBeforeDiscount('PLN'));
        $this->assertSame(3900, $small->fresh()->lowestPriceBeforeDiscount('EUR'));

        $this->get('/en/product/painted-mug')
            ->assertSeeInOrder(['€35.00', '€39.00', 'Lowest price in the 30 days before the reduction: €39.00']);
        $this->get('/produkt/malowany-kubek')
            ->assertSeeInOrder(['129,00 zł', '149,00 zł', 'Najniższa cena z 30 dni przed obniżką: 149,00 zł']);
    }

    public function test_the_english_basket_counts_in_euro_and_leaves_out_what_has_no_euro_price(): void
    {
        [$mug, $small, $large] = $this->mug();

        $this->post('/en/basket', ['variant_id' => $small->id], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['count' => 1, 'notice' => 'Painted mug — added to your basket'])
            ->assertJsonPath('content', fn (string $content) => str_contains($content, '€39.00') && str_contains($content, 'Total') && ! str_contains($content, 'zł'));

        // Nothing goes into a euro basket without a euro price.
        $this->post('/en/basket', ['variant_id' => $large->id], ['Accept' => 'application/json'])->assertNotFound();
        $this->post('/koszyk', ['variant_id' => $large->id])->assertRedirect();

        // The large size, added in złoty, stays out of the euro basket and comes back on a Polish page.
        $this->get('/en/shop')
            ->assertSee('data-count="1"', false)
            ->assertSee('href="'.url('/en/checkout').'"', false)
            ->assertSee('Go to checkout')
            ->assertDontSee('Przelewy24');
        $this->get('/sklep')
            ->assertSee('data-count="2"', false)
            ->assertSee('328,00 zł')
            ->assertSee('Przejdź do zamówienia')
            ->assertSee('Przelewy24');
    }

    public function test_the_gift_sets_wait_for_euro_on_the_english_side(): void
    {
        $this->get('/en/gift-sets')->assertNotFound();

        $this->get('/zestawy-prezentowe')->assertSee('href="'.url('/en/shop').'?unavailable=1" hreflang="en"', false);
    }

    public function test_the_free_delivery_threshold_stays_in_the_polish_basket(): void
    {
        [$mug, $small] = $this->mug();
        Setting::query()->updateOrCreate(['key' => 'free_shipping_threshold'], ['value' => json_encode(30000)]);

        $this->post('/en/basket', ['variant_id' => $small->id]);

        $this->get('/en/shop')->assertDontSee('more for free delivery')->assertSee('at checkout');
        $this->get('/sklep')->assertSee('Do darmowej wysyłki brakuje 151,00 zł');
    }

    /** @return array{Product, ProductVariant, ProductVariant} */
    private function mug(): array
    {
        $mug = $this->translatedProduct('Malowany kubek', 'Painted mug', 14900);
        $small = $mug->variants()->sole();
        $small->update(['label' => 'Mały']);
        $small->translations()->create(['locale' => 'en', 'label' => 'Small']);
        $small->prices()->create(['currency' => 'EUR', 'amount_minor' => 3900]);

        $large = ProductVariant::factory()->create(['product_id' => $mug->id, 'label' => 'Duży', 'price_gross' => 17900, 'stock' => 3]);
        $large->translations()->create(['locale' => 'en', 'label' => 'Large']);

        return [$mug->fresh(), $small->fresh(), $large->fresh()];
    }

    private function translatedProduct(string $name, string $english, int $price): Product
    {
        $category = Category::query()->first();

        if ($category === null) {
            $category = Category::factory()->create(['name' => 'Ceramika', 'slug' => 'ceramika']);
            $category->translations()->create(['locale' => 'en', 'name' => 'Ceramics', 'slug' => 'ceramics']);
        }

        $product = Product::factory()->create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'category_id' => $category->id, 'is_published' => true]);
        $product->translations()->create(['locale' => 'en', 'name' => $english, 'slug' => str($english)->slug()->toString()]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'label' => '', 'price_gross' => $price, 'stock' => 3]);

        return $product;
    }
}
