<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Enums\VariantKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The card's button behaves the same in the shop and in the gift finder (MA-259): a single size goes to the cart
 * without leaving the shelf, several sizes or lettering lead to the product page — the card never guesses.
 */
class ProductCardButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public static function pages(): array
    {
        return ['sklep' => ['/sklep'], 'prezenty' => ['/prezenty']];
    }

    #[DataProvider('pages')]
    public function test_a_single_size_goes_to_the_cart_from_the_card(string $page): void
    {
        $vase = $this->product('Wazon', [23900]);

        $this->get($page)
            ->assertOk()
            ->assertSeeInOrder(['239 zł', 'Wazon', 'action="'.route('cart.store').'"', 'name="variant_id" value="'.$vase->variants[0]->id.'"', 'Do koszyka', ': Wazon'], false);

        $this->postJson('/koszyk', ['variant_id' => $vase->variants[0]->id])->assertJson(['count' => 1]);
    }

    #[DataProvider('pages')]
    public function test_several_sizes_lead_to_the_product_page_instead_of_guessing_one(string $page): void
    {
        $bowls = $this->product('Miski', [6900, 7900, 8900], ['variant_kind' => VariantKind::Sizes]);
        $bowls->variants[0]->update(['stock' => 0]);

        $response = $this->get($page)->assertOk();

        $this->assertCardLink($response, $bowls, 'Wybierz rozmiar');
        foreach ($bowls->variants as $variant) {
            $response->assertDontSee('name="variant_id" value="'.$variant->id.'"', false);
        }
    }

    #[DataProvider('pages')]
    public function test_a_mug_with_your_lettering_is_left_to_the_mug_configurator(string $page): void
    {
        // Lettering of your own is chosen in the mug configurator with its preview (MA-275), not from a card.
        $stamped = $this->product('Kubek z Twoim tekstem', [7900], ['stamp_enabled' => true], stock: null);

        $this->get($page)->assertOk()
            ->assertDontSee('href="'.route('product.show', $stamped).'"', false)
            ->assertDontSee('name="variant_id"', false);
    }

    public function test_the_shop_and_the_gift_finder_draw_the_same_card(): void
    {
        $vase = $this->product('Wazon', [23900]);
        $bowls = $this->product('Miski', [6900, 7900], ['variant_kind' => VariantKind::Sizes]);

        foreach ([$vase, $bowls] as $product) {
            $this->assertSame($this->cardOf('/sklep', $product), $this->cardOf('/prezenty', $product));
        }
    }

    public function test_shelves_on_the_home_page_keep_the_card_without_a_button(): void
    {
        $this->product('Wazon', [23900]);

        $this->get('/')->assertOk()->assertDontSee('name="variant_id"', false);
    }

    private function assertCardLink(TestResponse $response, Product $product, string $label): void
    {
        $response->assertSeeInOrder([$product->name, 'href="'.route('product.show', $product).'"', $label, ': '.$product->name], false);
    }

    /**
     * One card's markup up to its heart, without what depends on the position on the shelf (delay, lazy loading).
     */
    private function cardOf(string $page, Product $product): string
    {
        $cards = explode('<div data-shelf-card', $this->get($page)->getContent());
        $card = collect($cards)->first(fn (string $card) => str_contains($card, 'href="'.route('product.show', $product).'"'));
        $this->assertNotNull($card, "No card for {$product->name} on {$page}");

        return preg_replace('/animation-delay: [\d.]+s|loading="lazy" ?/', '', str($card)->before('<button type="button" x-data x-cloak')->toString());
    }

    private function product(string $name, array $prices, array $attributes = [], ?int $stock = 3): Product
    {
        $product = Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'category_id' => Category::factory()->create()->id,
            'occasions' => ['birthday'],
            'recipients' => ['for_her'],
            ...$attributes,
        ]);

        foreach ($prices as $index => $price) {
            ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Rozmiar '.($index + 1), 'price_gross' => $price, 'stock' => $stock]);
        }

        return $product->load(['variants' => fn ($query) => $query->orderBy('id')]);
    }
}
