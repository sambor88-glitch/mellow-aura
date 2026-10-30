<?php

namespace Tests\Feature\Content;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Content\Support\PagePhotos;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The photos floating around the hero frame lead to the product Kasia picked in „Zdjęcia stron” (MA-280).
 */
class HomeFloatLinksTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->category = Category::factory()->create(['name' => 'Ceramika', 'slug' => 'ceramika']);
        $this->category->translations()->create(['locale' => 'en', 'name' => 'Ceramics', 'slug' => 'ceramics']);
    }

    public function test_a_photo_with_a_product_leads_to_its_page_and_the_others_stay_decoration(): void
    {
        $bowl = $this->product('Misa laguna', 'misa-laguna');
        $this->pick('home-float-1', $bowl);

        $page = $this->get('/')->assertOk();

        $page->assertSee('href="'.route('product.show', 'misa-laguna').'" aria-label="Zobacz Misa laguna"', false);
        $hero = $this->hero($page->getContent());
        $this->assertSame(1, substr_count($hero, 'aria-label="Zobacz'));
        // Five photos without a product stay hidden from a screen reader; the linked one does not hide its link.
        $this->assertSame(5, substr_count($hero, 'aria-hidden="true"'));
        $this->assertStringContainsString('aura-float-1" data-speed="1.4" data-depth="30">', $hero);
    }

    public function test_a_photo_without_a_product_has_no_link(): void
    {
        $this->product('Misa laguna', 'misa-laguna');

        $hero = $this->hero($this->get('/')->assertOk()->getContent());

        $this->assertStringNotContainsString('aria-label="Zobacz', $hero);
        $this->assertStringNotContainsString('<a ', $hero);
        $this->assertSame(6, substr_count($hero, 'aria-hidden="true"'));
    }

    public function test_a_hidden_sold_out_or_deleted_product_leaves_the_photo_without_a_link(): void
    {
        $hidden = $this->product('Ukryta misa', 'ukryta-misa');
        $soldOut = $this->product('Wyprzedany wazon', 'wyprzedany-wazon', stock: 0);
        $deleted = $this->product('Usunięta patera', 'usunieta-patera');
        $this->pick('home-float-1', $hidden);
        $this->pick('home-float-2', $soldOut);
        $this->pick('home-float-3', $deleted);
        $hidden->update(['is_published' => false]);
        $deleted->delete();

        $hero = $this->hero($this->get('/')->assertOk()->getContent());

        $this->assertStringNotContainsString('aria-label="Zobacz', $hero);
        $this->assertStringNotContainsString('/produkt/', $hero);
    }

    public function test_on_the_english_page_only_a_product_written_in_english_and_priced_in_euro_gets_a_link(): void
    {
        $translated = $this->product('Misa laguna', 'misa-laguna', english: true);
        $polishOnly = $this->product('Wazon rzeźbiony', 'wazon-rzezbiony');
        $this->pick('home-float-1', $translated);
        $this->pick('home-float-2', $polishOnly);

        $hero = $this->hero($this->get('/en')->assertOk()->getContent());

        $this->assertStringContainsString('href="'.url('/en/product/lagoon-bowl').'" aria-label="See Lagoon bowl"', $hero);
        $this->assertStringNotContainsString('wazon-rzezbiony', $hero);
        $this->assertSame(1, substr_count($hero, 'aria-label="See'));
    }

    public function test_the_panel_saves_the_product_and_takes_it_off(): void
    {
        $owner = User::factory()->create();
        $bowl = $this->product('Misa laguna', 'misa-laguna');

        $this->actingAs($owner)
            ->get('/panel/zdjecia-stron')
            ->assertOk()
            ->assertSee('Prowadzi do produktu')
            ->assertSee('<option value="'.$bowl->id.'" >Misa laguna</option>', false);

        $this->actingAs($owner)
            ->put('/panel/zdjecia-stron/home-float-4', ['focus' => ['x' => 50, 'y' => 50], 'product_id' => $bowl->id])
            ->assertRedirect('/panel/zdjecia-stron#home-float-4')
            ->assertSessionHasNoErrors();

        $this->assertSame($bowl->id, app(PagePhotos::class)->get('home-float-4')->productId);
        $this->get('/panel/zdjecia-stron')->assertSee('<option value="'.$bowl->id.'" selected>Misa laguna</option>', false);

        $this->actingAs($owner)
            ->put('/panel/zdjecia-stron/home-float-4', ['focus' => ['x' => 50, 'y' => 50], 'product_id' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull(app(PagePhotos::class)->get('home-float-4')->productId);
    }

    public function test_the_panel_refuses_a_product_that_does_not_exist_and_a_place_that_cannot_lead_anywhere(): void
    {
        $owner = User::factory()->create();
        $bowl = $this->product('Misa laguna', 'misa-laguna');

        $this->actingAs($owner)
            ->put('/panel/zdjecia-stron/home-float-1', ['focus' => ['x' => 50, 'y' => 50], 'product_id' => 999999])
            ->assertSessionHasErrors('product_id', null, 'zdjecie-home-float-1');
        $this->assertNull(app(PagePhotos::class)->get('home-float-1')->productId);

        $this->actingAs($owner)
            ->put('/panel/zdjecia-stron/home-kasia', ['focus' => ['x' => 50, 'y' => 50], 'product_id' => $bowl->id])
            ->assertSessionHasNoErrors();
        $this->assertNull(app(PagePhotos::class)->get('home-kasia')->productId);
    }

    public function test_a_new_photo_and_the_first_photo_back_drop_the_product(): void
    {
        $bowl = $this->product('Misa laguna', 'misa-laguna');
        $this->pick('home-float-5', $bowl);

        $this->actingAs(User::factory()->create())
            ->post('/panel/zdjecia-stron/home-float-5/przywroc')
            ->assertRedirect();

        $this->assertNull(app(PagePhotos::class)->get('home-float-5')->productId);
        $this->assertTrue(app(PagePhotos::class)->get('home-float-5')->isDefault);
    }

    private function product(string $name, string $slug, int $stock = 2, bool $english = false): Product
    {
        $product = Product::factory()->create(['name' => $name, 'slug' => $slug, 'category_id' => $this->category->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => $stock]);

        if ($english) {
            $product->translations()->create(['locale' => 'en', 'name' => 'Lagoon bowl', 'slug' => 'lagoon-bowl']);
            $variant->translations()->create(['locale' => 'en', 'label' => 'One size']);
            $variant->prices()->create(['currency' => 'EUR', 'amount_minor' => 2900]);
        }

        return $product;
    }

    private function pick(string $place, Product $product): void
    {
        app(SaveSettings::class)(app(PagePhotos::class)->with($place, ['product_id' => $product->id]));
    }

    /** The hero scene up to the frame: only the floating photos. */
    private function hero(string $html): string
    {
        $start = strpos($html, 'class="aura-scene"');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, 'class="aura-frame"', $start) - $start);
    }
}
