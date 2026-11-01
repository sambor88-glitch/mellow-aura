<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StockNotification;
use App\Modules\Catalog\Support\LastPieces;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * „Ostatnie sztuki” (MA-343): sold out and last pieces of the shop, with the threshold from the panel.
 */
class LastPiecesTest extends TestCase
{
    use RefreshDatabase;

    private Category $mugs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->mugs = Category::factory()->create(['name' => 'Kubki i filiżanki', 'slug' => 'kubki-i-filizanki', 'group' => CategoryGroup::Ceramics]);
    }

    public function test_it_counts_sold_out_and_last_pieces_apart_and_the_people_waiting(): void
    {
        $mug = $this->product('Kubek', ['S' => 0, 'M' => 1, 'L' => 5]);
        $this->product('Miska', ['' => 1]);
        $this->product('Talerz', ['' => null]);
        $this->product('Ukryty szkic', ['' => 0], ['is_published' => false]);
        $this->product('Wazon jedyny', ['' => 1], ['is_one_off' => true]);
        $this->product('Patera jedyna', ['' => 0], ['is_one_off' => true]);
        $hidden = $this->product('Dzbanek', ['' => 3, 'Stary' => 0]);
        $hidden->variants()->where('label', 'Stary')->update(['is_hidden' => true]);
        $this->waiting($mug->variants()->where('label', 'S')->sole(), 2);

        $this->assertSame(['soldOut' => 1, 'last' => 2, 'waiting' => 2, 'threshold' => 1], app(LastPieces::class)->summary());
    }

    public function test_the_threshold_comes_from_the_panel(): void
    {
        $this->product('Kubek', ['S' => 2, 'M' => 3]);
        Setting::query()->create(['key' => LastPieces::THRESHOLD, 'value' => 2]);

        $this->assertSame(['soldOut' => 0, 'last' => 1, 'waiting' => 0, 'threshold' => 2], app(LastPieces::class)->summary());
    }

    public function test_kasia_sets_the_threshold_in_the_product_settings(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get('/panel/produkty/ustawienia')
            ->assertOk()
            ->assertSee('Ostatnie sztuki')
            ->assertSee('name="last_pieces_threshold"', false)
            ->assertSee('value="1"', false);

        $this->actingAs($owner)
            ->put('/panel/produkty/ostatnie-sztuki', ['last_pieces_threshold' => '3'])
            ->assertRedirect(route('admin.products.settings').'#ostatnie-sztuki');
        $this->assertSame(3, app(LastPieces::class)->threshold());

        $this->actingAs($owner)
            ->from('/panel/produkty/ustawienia')
            ->put('/panel/produkty/ostatnie-sztuki', ['last_pieces_threshold' => '0'])
            ->assertSessionHasErrors(['last_pieces_threshold' => 'Wpisz co najmniej 1 sztukę']);
        $this->assertSame(3, app(LastPieces::class)->threshold());
    }

    public function test_the_list_opens_on_last_pieces_and_says_who_waits(): void
    {
        $mug = $this->product('Kubek', ['S' => 0, 'M' => 2, 'L' => 5]);
        $this->product('Talerz', ['' => 4]);
        $this->product('Wazon jedyny', ['' => 1], ['is_one_off' => true]);
        $this->waiting($mug->variants()->where('label', 'S')->sole(), 3);
        Setting::query()->create(['key' => LastPieces::THRESHOLD, 'value' => 2]);

        $page = $this->actingAs(User::factory()->create())
            ->get('/panel/produkty?pokaz=ostatnie-sztuki')
            ->assertOk()
            ->assertSee('Ostatnie sztuki')
            ->assertSee('Ostatnie sztuki: M — 2 szt.')
            ->assertSee('Brak: S')
            ->assertSee('3 osoby czekają na powrót (S: 3)')
            ->assertDontSee('Kończą się');

        $content = $page->getContent();
        $this->assertStringContainsString('&quot;filter&quot;:&quot;low&quot;', $content);
        $this->assertMatchesRegularExpression('/data-id="'.$mug->id.'" data-states="live low"/', $content);
        // A one-off piece is one by nature: no alarm, no „Ostatnia sztuka”.
        $vase = Product::where('name', 'Wazon jedyny')->sole();
        $this->assertMatchesRegularExpression('/data-id="'.$vase->id.'" data-states="live( todo)?"/', $content);
        $this->assertStringNotContainsString('Ostatnia sztuka', $content);
    }

    public function test_the_list_without_a_filter_link_opens_as_saved(): void
    {
        $this->product('Kubek', ['' => 2]);

        $content = $this->actingAs(User::factory()->create())->get('/panel/produkty?pokaz=cokolwiek')->assertOk()->getContent();

        $this->assertStringContainsString('&quot;filter&quot;:null', $content);
        $this->assertStringNotContainsString('Ostatnie sztuki', $content);
    }

    /**
     * @param  array<string, ?int>  $sizes  label => pieces on the shelf
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, array $sizes, array $attributes = []): Product
    {
        $product = Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'category_id' => $this->mugs->id,
            'is_published' => true,
            ...$attributes,
        ]);

        foreach ($sizes as $label => $stock) {
            ProductVariant::factory()->create(['product_id' => $product->id, 'label' => (string) $label, 'price_gross' => 7900, 'stock' => $stock]);
        }

        return $product;
    }

    private function waiting(ProductVariant $variant, int $people): void
    {
        foreach (range(1, $people) as $person) {
            StockNotification::query()->create(['product_variant_id' => $variant->id, 'email' => "osoba{$person}@example.com", 'locale' => 'pl', 'token' => Str::random(40)]);
        }
    }
}
