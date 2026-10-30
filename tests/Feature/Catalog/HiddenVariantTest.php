<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Mail\BackInStock;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A size hidden in the panel (MA-279): off the shop, its history kept, back when ticked off.
 */
class HiddenVariantTest extends TestCase
{
    use RefreshDatabase;

    private Category $mugs;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
        $this->mugs = Category::factory()->create(['name' => 'Kubki i filiżanki', 'slug' => 'kubki-i-filizanki', 'group' => CategoryGroup::Ceramics]);
    }

    public function test_a_hidden_size_is_off_the_product_page_and_its_old_link_opens_the_first_size_on_show(): void
    {
        [$product, $sun, $moon] = $this->quoteMugs();
        $moon->update(['is_hidden' => true]);

        $this->get('/produkt/kubki-z-cytatem')
            ->assertOk()
            ->assertSee('Słońce')
            ->assertDontSee('Księżyc');

        $this->get('/produkt/kubki-z-cytatem?wariant='.$moon->id)
            ->assertOk()
            ->assertSee('<input type="hidden" name="variant_id" value="'.$sun->id.'">', false)
            ->assertDontSee('Księżyc');

        $this->assertTrue(Product::query()->live()->whereKey($product->id)->exists());
    }

    public function test_a_product_with_every_size_hidden_leaves_the_shelf_and_its_page_leads_to_its_category(): void
    {
        [$product, $sun, $moon] = $this->quoteMugs();
        ProductVariant::query()->whereKey([$sun->id, $moon->id])->update(['is_hidden' => true]);

        $this->assertFalse(Product::query()->live()->whereKey($product->id)->exists());
        $this->get('/produkt/kubki-z-cytatem')->assertRedirect('/sklep/kubki-i-filizanki');
        $this->get('/sklep')->assertOk()->assertDontSee('Kubki z cytatem');
        $this->assertStringNotContainsString(url('/produkt/kubki-z-cytatem'), $this->get('/sitemap.xml')->getContent());
    }

    public function test_a_hidden_size_cannot_go_in_the_basket_and_drops_out_of_it(): void
    {
        [, $sun, $moon] = $this->quoteMugs();

        $this->post('/koszyk', ['variant_id' => $sun->id])->assertRedirect();
        $this->post('/koszyk', ['variant_id' => $moon->id])->assertRedirect();
        $this->get('/sklep')->assertSeeInOrder(['Twój koszyk', 'Słońce', 'Księżyc']);

        $moon->update(['is_hidden' => true]);

        $this->post('/koszyk', ['variant_id' => $moon->id])->assertNotFound();
        $this->get('/sklep')->assertSeeInOrder(['Twój koszyk', 'Słońce'])->assertDontSee('Księżyc');
    }

    public function test_a_hidden_size_is_not_in_the_feed_nor_in_the_structured_data(): void
    {
        [$product, $sun, $moon] = $this->quoteMugs();
        $product->addMedia(base_path('zdjecia/talerz-niebieski-odcisk.webp'))->preservingOriginal()->toMediaCollection('images');
        $moon->update(['is_hidden' => true]);

        $feed = $this->get('/google-merchant.xml')->assertOk()->getContent();
        $this->assertStringContainsString('v'.$sun->id, $feed);
        $this->assertStringNotContainsString('v'.$moon->id, $feed);
        $this->assertStringNotContainsString('?wariant='.$moon->id, $this->get('/produkt/kubki-z-cytatem')->getContent());
    }

    public function test_hiding_in_the_panel_keeps_the_history_and_unhiding_tells_the_people_waiting(): void
    {
        Mail::fake();
        [$product, $sun, $moon] = $this->quoteMugs();
        $moon->update(['stock' => 0]);
        StockNotification::create(['product_variant_id' => $moon->id, 'email' => 'ania@example.com', 'locale' => 'pl', 'token' => str_repeat('a', 40)]);
        $admin = User::factory()->create();
        $form = fn (bool $hidden, string $stock) => [
            'form' => 'produkt-'.$product->id,
            'name' => 'Kubki z cytatem',
            'category_id' => $this->mugs->id,
            'is_published' => '1',
            'variants' => [
                ['id' => $sun->id, 'label' => 'Słońce', 'price' => '89', 'stock' => ''],
                ['id' => $moon->id, 'label' => 'Księżyc', 'price' => '89', 'stock' => $stock, 'is_hidden' => $hidden ? '1' : null],
            ],
        ];

        $this->actingAs($admin)->get('/panel/produkty')->assertOk()->assertSee('name="variants[1][is_hidden]"', false);

        // Back in stock while hidden: she keeps waiting, the size is not on the page yet.
        $this->actingAs($admin)->put('/panel/produkty/'.$product->id, $form(true, '2'))->assertRedirect('/panel/produkty#produkt-'.$product->id);

        $this->assertTrue($moon->fresh()->is_hidden);
        $this->assertSame(1, $moon->priceHistory()->count());
        $this->assertSame(1, StockNotification::count());
        Mail::assertNothingQueued();
        $this->actingAs($admin)->get('/panel/produkty')->assertSee('name="variants[1][is_hidden]" value="1" checked', false);

        $this->actingAs($admin)->put('/panel/produkty/'.$product->id, $form(false, '2'));

        $this->assertFalse($moon->fresh()->is_hidden);
        Mail::assertQueued(BackInStock::class, 1);
        $this->assertSame(0, StockNotification::count());
    }

    public function test_a_hidden_sold_out_size_takes_no_sign_ups(): void
    {
        [, , $moon] = $this->quoteMugs();
        $moon->update(['stock' => 0, 'is_hidden' => true]);

        $this->post('/powiadom-o-dostepnosci', ['variant_id' => $moon->id, 'email' => 'ania@example.com', 'consent' => '1'])->assertNotFound();
        $this->assertSame(0, StockNotification::count());
    }

    /**
     * @return array{Product, ProductVariant, ProductVariant}
     */
    private function quoteMugs(): array
    {
        $product = Product::factory()->create(['slug' => 'kubki-z-cytatem', 'name' => 'Kubki z cytatem', 'category_id' => $this->mugs->id]);
        $sun = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Słońce', 'price_gross' => 8900, 'stock' => null]);
        $moon = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Księżyc', 'price_gross' => 8900, 'stock' => 3]);

        return [$product->fresh(), $sun, $moon];
    }
}
