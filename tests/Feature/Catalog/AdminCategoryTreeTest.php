<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tree of shelves in the panel (MA-351): a new shelf, dragging, moving on the card, renaming, deleting an empty one
 * and „Pokaż też w” on the product card.
 */
class AdminCategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    private Category $clay;

    private Category $sewing;

    private Category $jewellery;

    private Category $earrings;

    private Category $vouchers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $this->clay = Category::factory()->create(['name' => 'Glina', 'slug' => 'glina', 'group' => CategoryGroup::Ceramics, 'sort_order' => 1]);
        $this->sewing = Category::factory()->create(['name' => 'Szycie', 'slug' => 'szycie', 'group' => CategoryGroup::Crafts, 'sort_order' => 2]);
        $this->vouchers = Category::factory()->create(['name' => 'Vouchery', 'slug' => 'vouchery', 'group' => CategoryGroup::Workshops, 'sort_order' => 3]);
        $this->jewellery = Category::factory()->create(['name' => 'Biżuteria', 'slug' => 'bizuteria', 'parent_id' => $this->clay->id, 'sort_order' => 1]);
        $this->earrings = Category::factory()->create(['name' => 'Kolczyki', 'slug' => 'kolczyki', 'parent_id' => $this->jewellery->id, 'sort_order' => 1]);
    }

    public function test_the_screen_shows_the_tree_and_keeps_the_vouchers_outside_it(): void
    {
        $this->get('/panel/kategorie')
            ->assertOk()
            ->assertSeeInOrder(['id="galaz-'.$this->clay->id.'"', 'data-children-of="'.$this->clay->id.'"', 'data-id="'.$this->jewellery->id.'"', 'data-children-of="'.$this->jewellery->id.'"', 'data-id="'.$this->earrings->id.'"', 'id="galaz-'.$this->sewing->id.'"', 'Poza Gliną i Szyciem', 'Vouchery'], false)
            ->assertSee('pusta — nie widać jej w sklepie')
            // A third level takes no more shelves under it.
            ->assertDontSee('aria-label="Dodaj półkę w: Kolczyki"', false)
            ->assertSee('aria-label="Dodaj półkę w: Biżuteria"', false)
            ->assertSee('Glina › Biżuteria › Kolczyki');
    }

    public function test_a_new_shelf_takes_its_branch_group_and_an_address_that_is_free(): void
    {
        Category::factory()->create(['name' => 'Opaski', 'slug' => 'opaski', 'parent_id' => $this->sewing->id, 'group' => CategoryGroup::Crafts, 'sort_order' => 1]);

        $this->post('/panel/kategorie', ['name' => ' Opaski ', 'en' => ['name' => 'Headbands'], 'parent_id' => $this->sewing->id])
            ->assertSessionHasNoErrors();

        $shelf = Category::query()->latest('id')->first();
        $this->assertSame(['Opaski', 'opaski-2', $this->sewing->id, CategoryGroup::Crafts, 2], [$shelf->name, $shelf->slug, $shelf->parent_id, $shelf->group, $shelf->sort_order]);
        $this->assertSame(['Headbands', 'headbands'], [$shelf->translation('en')->name, $shelf->translation('en')->slug]);

        $this->post('/panel/kategorie', ['name' => 'Kolczyki wiszące', 'parent_id' => $this->jewellery->id])->assertSessionHasNoErrors();
        $this->assertSame($this->jewellery->id, Category::query()->where('slug', 'kolczyki-wiszace')->value('parent_id'));
        $this->assertFalse(Category::query()->where('slug', 'kolczyki-wiszace')->first()->hasTranslation('en'));
    }

    public function test_a_new_shelf_never_goes_deeper_than_three_levels_or_among_the_vouchers(): void
    {
        $this->post('/panel/kategorie', ['name' => 'Sztyfty', 'parent_id' => $this->earrings->id])
            ->assertSessionHasErrorsIn('nowa-polka', ['parent_id']);
        $this->post('/panel/kategorie', ['name' => 'Kwotowe', 'parent_id' => $this->vouchers->id])
            ->assertSessionHasErrorsIn('nowa-polka', ['parent_id']);
        $this->post('/panel/kategorie', ['name' => '', 'parent_id' => $this->clay->id])
            ->assertSessionHasErrorsIn('nowa-polka', ['name']);

        $this->assertSame(5, Category::count());
    }

    public function test_a_drag_saves_the_whole_tree_and_a_shelf_moved_to_the_other_branch_takes_its_group(): void
    {
        $vases = Category::factory()->create(['name' => 'Wazony', 'slug' => 'wazony', 'parent_id' => $this->clay->id, 'sort_order' => 2]);

        // Biżuteria with Kolczyki goes to Szycie, below nothing; Wazony stays in Glina.
        $this->putJson('/panel/kategorie/kolejnosc', ['tree' => [
            ['id' => $vases->id, 'parent_id' => $this->clay->id],
            ['id' => $this->jewellery->id, 'parent_id' => $this->sewing->id],
            ['id' => $this->earrings->id, 'parent_id' => $this->jewellery->id],
        ]])->assertOk()->assertJson(['message' => 'Kolejność zapisana. Tak stoi teraz w sklepie.']);

        $this->assertSame([$this->clay->id, 1], [$vases->fresh()->parent_id, $vases->fresh()->sort_order]);
        $this->assertSame([$this->sewing->id, CategoryGroup::Crafts], [$this->jewellery->fresh()->parent_id, $this->jewellery->fresh()->group]);
        // Everything under it follows: the care rule of sewn things.
        $this->assertSame(CategoryGroup::Crafts, $this->earrings->fresh()->group);
    }

    public function test_a_drag_too_deep_or_into_itself_or_on_an_old_list_is_refused(): void
    {
        $vases = Category::factory()->create(['name' => 'Wazony', 'slug' => 'wazony', 'parent_id' => $this->clay->id, 'sort_order' => 2]);

        // Biżuteria with Kolczyki under Wazony would make four levels.
        $this->putJson('/panel/kategorie/kolejnosc', ['tree' => [
            ['id' => $vases->id, 'parent_id' => $this->clay->id],
            ['id' => $this->jewellery->id, 'parent_id' => $vases->id],
            ['id' => $this->earrings->id, 'parent_id' => $this->jewellery->id],
        ]])->assertUnprocessable()->assertJson(['message' => 'Tak głęboko się nie da — najwyżej trzy poziomy, np. Glina › Biżuteria › Kolczyki.']);

        $this->putJson('/panel/kategorie/kolejnosc', ['tree' => [
            ['id' => $vases->id, 'parent_id' => $this->clay->id],
            ['id' => $this->jewellery->id, 'parent_id' => $this->earrings->id],
            ['id' => $this->earrings->id, 'parent_id' => $this->jewellery->id],
        ]])->assertUnprocessable();

        // Among the vouchers, outside the tree.
        $this->putJson('/panel/kategorie/kolejnosc', ['tree' => [
            ['id' => $vases->id, 'parent_id' => $this->vouchers->id],
            ['id' => $this->jewellery->id, 'parent_id' => $this->clay->id],
            ['id' => $this->earrings->id, 'parent_id' => $this->jewellery->id],
        ]])->assertUnprocessable();

        // A shelf added in another window in the meantime: the list on the screen is old.
        $this->putJson('/panel/kategorie/kolejnosc', ['tree' => [
            ['id' => $this->jewellery->id, 'parent_id' => $this->clay->id],
            ['id' => $this->earrings->id, 'parent_id' => $this->jewellery->id],
        ]])->assertConflict();

        $this->assertSame($this->clay->id, $this->jewellery->fresh()->parent_id);
        $this->assertSame($this->clay->id, $vases->fresh()->parent_id);
    }

    public function test_the_card_renames_moves_and_names_a_shelf_in_english_without_changing_its_address(): void
    {
        $form = 'kategoria-'.$this->earrings->id;

        $this->put('/panel/kategorie/'.$this->earrings->id, ['form' => $form, 'name' => 'Kolczyki z gliny', 'parent_id' => $this->clay->id, 'en' => ['name' => 'Clay earrings']])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/panel/kategorie#'.$form);

        $earrings = $this->earrings->fresh();
        $this->assertSame(['Kolczyki z gliny', 'kolczyki', $this->clay->id, 2], [$earrings->name, $earrings->slug, $earrings->parent_id, $earrings->sort_order]);
        $this->assertSame('clay-earrings', $earrings->translation('en')->slug);

        // An emptied English name takes the shelf off /en/.
        $this->put('/panel/kategorie/'.$this->earrings->id, ['form' => $form, 'name' => 'Kolczyki z gliny', 'en' => ['name' => '']])->assertSessionHasNoErrors();
        $this->assertFalse($this->earrings->fresh()->hasTranslation('en'));

        // Biżuteria cannot go under its own Kolczyki.
        $this->earrings->fresh()->update(['parent_id' => $this->jewellery->id]);
        $this->put('/panel/kategorie/'.$this->jewellery->id, ['form' => 'kategoria-'.$this->jewellery->id, 'name' => 'Biżuteria', 'parent_id' => $this->earrings->id])
            ->assertSessionHasErrorsIn('kategoria-'.$this->jewellery->id, ['parent_id']);
        $this->assertSame($this->clay->id, $this->jewellery->fresh()->parent_id);
        $this->put('/panel/kategorie/'.$this->jewellery->id, ['form' => 'kategoria-'.$this->jewellery->id, 'name' => ''])
            ->assertSessionHasErrorsIn('kategoria-'.$this->jewellery->id, ['name']);
    }

    public function test_only_an_empty_shelf_can_be_deleted(): void
    {
        $product = Product::factory()->create(['category_id' => $this->earrings->id]);

        $this->get('/panel/kategorie')->assertSee('Najpierw przenieś produkty z tej półki na inną');

        $this->delete('/panel/kategorie/'.$this->earrings->id)->assertSessionHas('panel_status', 'Najpierw przenieś produkty z tej półki na inną — w karcie produktu, „Rodzaj w sklepie”.');
        $this->delete('/panel/kategorie/'.$this->jewellery->id)->assertSessionHas('panel_status', 'Najpierw przenieś albo usuń półki, które są w „Biżuteria”.');
        $this->delete('/panel/kategorie/'.$this->clay->id)->assertSessionHas('panel_status', 'Kategorii głównej nie usuniesz — możesz zmienić jej nazwę.');

        $product->update(['category_id' => $this->jewellery->id]);
        $product->alsoInCategories()->attach($this->earrings->id);
        $this->delete('/panel/kategorie/'.$this->earrings->id)->assertSessionHas('panel_status', 'Najpierw odznacz tę półkę w „Pokaż też w” przy produktach, które się na niej pokazują.');

        $product->alsoInCategories()->detach();
        $this->delete('/panel/kategorie/'.$this->earrings->id)->assertSessionHas('panel_status', 'Kolczyki — usunięta.');
        $this->assertModelMissing($this->earrings);
        $this->assertModelExists($this->jewellery);
    }

    public function test_the_product_card_picks_its_shelf_from_the_tree_and_shows_it_on_others_too(): void
    {
        $incense = Category::factory()->create(['name' => 'Kadzielnice', 'slug' => 'kadzielnice', 'parent_id' => $this->clay->id, 'sort_order' => 2]);
        $stands = Product::factory()->create(['name' => 'Podstawki', 'category_id' => $this->jewellery->id]);
        ProductVariant::factory()->create(['product_id' => $stands->id, 'price_gross' => 7900, 'stock' => 3]);

        $this->get('/panel/produkty/'.$stands->id)
            ->assertOk()
            ->assertSee('>Glina › Biżuteria › Kolczyki</option>', false)
            ->assertSee('Pokaż też w')
            ->assertSee('name="also_in[]" value="'.$incense->id.'"', false);

        $form = $this->get('/panel/produkty/'.$stands->id)->getContent();
        $this->assertStringContainsString('name="also_in_sent"', $form);

        $this->put('/panel/produkty/'.$stands->id, [
            ...$this->productForm($stands),
            'also_in_sent' => '1',
            // The main shelf is left out: it is already where the product stands.
            'also_in' => [$incense->id, $this->jewellery->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$incense->id], $stands->fresh()->alsoInCategories->modelKeys());

        // A form without the list (an older tab) leaves the other shelves alone.
        $this->put('/panel/produkty/'.$stands->id, $this->productForm($stands))->assertSessionHasNoErrors();
        $this->assertSame([$incense->id], $stands->fresh()->alsoInCategories->modelKeys());

        // „Duplikuj” takes them along.
        $this->post('/panel/produkty/'.$stands->id.'/duplikuj');
        $this->assertSame([$incense->id], Product::query()->latest('id')->first()->alsoInCategories->modelKeys());
    }

    /**
     * @return array<string, mixed>
     */
    private function productForm(Product $product): array
    {
        $variant = $product->variants()->first();

        return [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'is_published' => '1',
            'variants' => [['id' => $variant->id, 'label' => '', 'price' => '79', 'stock' => '3']],
        ];
    }
}
