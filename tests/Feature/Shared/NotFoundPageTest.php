<?php

namespace Tests\Feature\Shared;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The missing page keeps the site's header with the menu and the cart and the footer (MA-259).
 */
class NotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_an_unknown_address_shows_the_menu_the_cart_and_the_footer_with_a_404(): void
    {
        $this->get('/nie-ma-takiej-strony')
            ->assertNotFound()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('data-aura-header', false)
            ->assertSeeInOrder(['aria-label="Główne menu"', 'href="'.route('shop.index').'"', 'Sklep', 'Prezenty'], false)
            ->assertSee('aria-controls="cart"', false)
            ->assertSeeInOrder(['<main id="tresc"', 'nie ma tej strony', '<h1', 'Tej strony tu nie ma', 'Adres mógł się zmienić'], false)
            ->assertSeeInOrder(['href="'.route('shop.index').'"', 'Przejdź do sklepu', 'href="'.route('home').'"', 'Strona główna', '</main>', '<footer'], false)
            ->assertDontSee('404');
    }

    public function test_the_header_counts_what_is_already_in_the_cart(): void
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => 6900, 'stock' => 3]);
        $this->postJson('/koszyk', ['variant_id' => $variant->id])->assertJson(['count' => 1]);

        $this->get('/nie-ma-takiej-strony')->assertNotFound()->assertSee('<span x-text="$store.cart.count">1</span>', false);
    }

    public function test_a_missing_product_or_category_gets_the_same_page(): void
    {
        $this->get('/sklep/nie-ma-takiej')->assertNotFound()->assertSee('Tej strony tu nie ma')->assertSee('data-aura-header', false);
        $this->get('/produkt/nie-ma-takiego')->assertNotFound()->assertSee('Tej strony tu nie ma')->assertSee('<footer', false);
    }

    public function test_the_standalone_page_takes_over_when_the_layout_fails(): void
    {
        View::composer('shared::errors.not-found', fn () => throw new \RuntimeException('Baza nie odpowiada'));

        $this->get('/nie-ma-takiej-strony')
            ->assertNotFound()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('Tej strony tu nie ma')
            ->assertSee('Przejdź do sklepu')
            ->assertDontSee('data-aura-header', false);
    }

    public function test_a_request_for_json_still_gets_json(): void
    {
        $this->getJson('/nie-ma-takiej-strony')->assertNotFound()->assertJsonStructure(['message']);
    }
}
