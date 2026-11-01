<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CategoryRedirect;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\ProductMerchantItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The shop with its shelves in a tree (MA-351): Glina and Szycie, the shelves under them and the ones under those.
 */
class CategoryTreeShopTest extends TestCase
{
    use RefreshDatabase;

    private Category $clay;

    private Category $sewing;

    private Category $jewellery;

    private Category $earrings;

    private Category $incense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->clay = $this->category('Glina', 'glina', null, 1, ['Ceramics', 'ceramics']);
        $this->sewing = $this->category('Szycie', 'szycie', null, 2, ['Textiles', 'textiles'], CategoryGroup::Crafts);
        $this->jewellery = $this->category('Biżuteria', 'bizuteria', $this->clay, 1, ['Jewellery', 'jewellery']);
        $this->earrings = $this->category('Kolczyki', 'kolczyki', $this->jewellery, 1, ['Earrings', 'earrings']);
        $this->incense = $this->category('Kadzielnice', 'kadzielnice', $this->clay, 2, ['Incense holders', 'incense-holders']);
    }

    public function test_a_shelf_shows_what_stands_on_the_shelves_under_it_and_what_is_also_shown_on_it_once(): void
    {
        $hoops = $this->product('Kolczyki koła', $this->earrings);
        $stands = $this->product('Podstawki pod biżuterię', $this->jewellery);
        $stands->alsoInCategories()->attach([$this->incense->id, $this->clay->id]);
        $this->product('Kadzielnica', $this->incense);
        $this->product('Scrunchies', $this->category('Scrunchie', 'scrunchie', $this->sewing, 1, null, CategoryGroup::Crafts));

        $this->get('/sklep/glina')
            ->assertOk()
            ->assertSee('Kolczyki koła')
            ->assertSee('Kadzielnica')
            ->assertDontSee('Scrunchies')
            // Shown on Glina itself and on a shelf under it, it is still one card.
            ->assertSee('3 z 4 produktów na półce');

        $this->get('/sklep/kadzielnice')
            ->assertSee('Kadzielnica')
            ->assertSee('Podstawki pod biżuterię')
            ->assertDontSee('Kolczyki koła');

        $this->get('/sklep/kolczyki')->assertSee('Kolczyki koła')->assertDontSee('Podstawki pod biżuterię');
        $this->get('/sklep')->assertSee('4 z 4 produktów na półce');
        $this->assertSame([$this->jewellery->id, $this->incense->id, $this->clay->id], $stands->fresh()->shelfIds());
        $this->assertNotNull($hoops);
    }

    public function test_the_chips_open_one_row_per_level_of_the_branch(): void
    {
        $this->product('Kolczyki koła', $this->earrings);
        $this->product('Kadzielnica', $this->incense);
        // Nothing on it: no chip, until it is the open one.
        $this->category('Zegary', 'zegary', $this->clay, 3);

        $this->get('/sklep')
            ->assertSeeInOrder(['>Wszystkie <span class="ml-0.5 text-[12px] opacity-85">2</span>', '>Glina <span class="ml-0.5 text-[12px] opacity-85">2</span>'], false)
            // Only the top row on the whole shop; Szycie has nothing yet.
            ->assertDontSee('>Biżuteria <span', false)
            ->assertDontSee('>Szycie <span', false);

        $this->get('/sklep/kolczyki')
            ->assertOk()
            ->assertSeeInOrder([
                'href="'.url('/sklep/glina').'"', 'data-chosen', '>Glina <span',
                'aria-label="Glina — podkategorie" role="group"',
                'href="'.url('/sklep/bizuteria').'"', 'data-chosen', '>Biżuteria <span',
                '>Kadzielnice <span',
                'aria-label="Biżuteria — podkategorie" role="group"',
                'href="'.url('/sklep/kolczyki').'"', 'data-chosen', 'aria-current="page"', '>Kolczyki <span',
            ], false)
            ->assertDontSee('>Zegary <span', false);

        $this->get('/sklep/zegary')->assertOk()->assertSee('>Zegary <span class="ml-0.5 text-[12px] opacity-85">0</span>', false);
    }

    public function test_breadcrumbs_and_their_markup_walk_down_the_whole_branch(): void
    {
        $hoops = $this->product('Kolczyki koła', $this->earrings);

        $response = $this->get('/sklep/kolczyki')
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Ścieżka nawigacji"', 'Strona główna', 'href="'.url('/sklep').'"', 'href="'.url('/sklep/glina').'"', 'Glina', 'href="'.url('/sklep/bizuteria').'"', 'Biżuteria', '<span aria-current="page" class="text-lead">Kolczyki</span>'], false);

        $trail = $this->structuredData($response->getContent())->firstWhere('@type', 'BreadcrumbList')['itemListElement'];
        $this->assertSame(['Strona główna', 'Sklep', 'Glina', 'Biżuteria', 'Kolczyki'], array_column($trail, 'name'));
        $this->assertSame(url('/sklep/bizuteria'), $trail[3]['item']);

        $page = $this->get('/produkt/'.$hoops->slug)
            ->assertOk()
            ->assertSeeInOrder(['href="'.url('/sklep/glina').'"', 'href="'.url('/sklep/bizuteria').'"', 'href="'.url('/sklep/kolczyki').'"', '<span aria-current="page" class="text-lead">Kolczyki koła</span>'], false);
        $trail = $this->structuredData($page->getContent())->firstWhere('@type', 'BreadcrumbList')['itemListElement'];
        $this->assertSame(['Sklep', 'Glina', 'Biżuteria', 'Kolczyki', 'Kolczyki koła'], array_column($trail, 'name'));
    }

    public function test_an_empty_shelf_stays_out_of_google_and_the_sitemap(): void
    {
        $this->product('Kolczyki koła', $this->earrings);
        $clocks = $this->category('Zegary', 'zegary', $this->clay, 3);

        $this->get('/sklep/zegary')->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
        $this->get('/sklep/glina')->assertOk()->assertDontSee('<meta name="robots" content="noindex">', false);

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();

        // Glina and Biżuteria have nothing of their own, but a shelf under them does.
        foreach (['glina', 'bizuteria', 'kolczyki'] as $slug) {
            $this->assertStringContainsString('<loc>'.url('/sklep/'.$slug).'</loc>', $sitemap);
        }

        $this->assertStringNotContainsString(url('/sklep/'.$clocks->slug), $sitemap);
        $this->assertStringNotContainsString(url('/sklep/kadzielnice'), $sitemap);
    }

    public function test_an_old_address_goes_on_to_the_shelf_in_both_languages(): void
    {
        $this->product('Kolczyki koła', $this->earrings, euro: 2900);
        CategoryRedirect::create(['category_id' => $this->jewellery->id, 'locale' => 'pl', 'slug' => 'bizuteria-i-dodatki']);
        CategoryRedirect::create(['category_id' => $this->jewellery->id, 'locale' => 'en', 'slug' => 'jewellery-and-more']);

        $this->get('/sklep/bizuteria-i-dodatki?sort=name')->assertStatus(301)->assertRedirect(url('/sklep/bizuteria?sort=name'));
        $this->get('/en/shop/jewellery-and-more')->assertStatus(301)->assertRedirect(url('/en/shop/jewellery'));
        // A Polish old address is not an English one.
        $this->get('/en/shop/bizuteria-i-dodatki')->assertNotFound();
        $this->get('/sklep/nie-ma-takiej')->assertNotFound();
    }

    public function test_on_english_pages_a_shelf_under_one_without_an_english_name_has_no_page(): void
    {
        $this->product('Kolczyki koła', $this->earrings, euro: 2900);
        $this->jewellery->translations()->delete();

        $this->get('/en/shop/earrings')->assertNotFound();
        $this->get('/en/shop/ceramics')->assertOk()->assertDontSee('Jewellery');
    }

    public function test_the_personalised_mugs_open_with_the_mug_configurator(): void
    {
        $mugs = $this->category('Kubki z własnym napisem', Category::PERSONALISED_MUGS, $this->clay, 3, ['Personalised mugs', 'personalised-mugs']);

        // The configurator counts as something on the shelf, so the shelf has a chip and Google sees it.
        $this->get('/sklep/glina')->assertSee('>Kubki z własnym napisem <span class="ml-0.5 text-[12px] opacity-85">1</span>', false);

        $this->get('/sklep/'.$mugs->slug)
            ->assertOk()
            ->assertSeeInOrder(['Zrobię dla Ciebie', 'Kubek z Twoim napisem', 'Wpisz napis'])
            ->assertSee('href="'.route('mug.index').'" class="group flex', false)
            ->assertDontSee('<meta name="robots" content="noindex">', false)
            ->assertDontSee('Nic takiego teraz nie stoi na półce');

        $this->get('/sklep/kolczyki')->assertDontSee('Wpisz napis');
    }

    public function test_google_gets_the_whole_branch_as_the_product_type(): void
    {
        $hoops = $this->product('Kolczyki koła', $this->earrings, attributes: ['show_in_google' => true]);
        Storage::fake('public');
        $hoops->addMedia(base_path('zdjecia/talerz-niebieski-odcisk.webp'))->preservingOriginal()->toMediaCollection('images');

        $item = collect(app(ProductMerchantItems::class)())->first();

        $this->assertSame('Glina > Biżuteria > Kolczyki', $item->productType);
    }

    /**
     * @param  array{string, string}|null  $english
     */
    private function category(string $name, string $slug, ?Category $parent, int $position, ?array $english = null, CategoryGroup $group = CategoryGroup::Ceramics): Category
    {
        $category = Category::factory()->create(['name' => $name, 'slug' => $slug, 'parent_id' => $parent?->id, 'group' => $group, 'sort_order' => $position]);

        if ($english !== null) {
            $category->translations()->create(['locale' => 'en', 'name' => $english[0], 'slug' => $english[1]]);
        }

        return $category;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, Category $category, ?int $euro = null, array $attributes = []): Product
    {
        $product = Product::factory()->create(['name' => $name, 'category_id' => $category->id, 'is_published' => true, ...$attributes]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => 8900, 'stock' => 2]);

        if ($euro !== null) {
            $product->translations()->create(['locale' => 'en', 'name' => $name.' (en)', 'slug' => str($name)->slug().'-en']);
            $variant->prices()->create(['currency' => 'EUR', 'amount_minor' => $euro]);
        }

        return $product;
    }
}
