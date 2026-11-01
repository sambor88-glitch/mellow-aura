<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The staging database on the day of MA-351: eight flat categories with products, some texts from Kasia, and the
 * migration that puts them in the tree under Glina and Szycie.
 */
class CategoryTreeMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Category> */
    private array $old = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        foreach ([
            'kubki-i-filizanki' => ['Kubki i filiżanki', CategoryGroup::Ceramics, ['Mugs & cups', 'mugs-and-cups']],
            'talerze-i-miski' => ['Talerze i miski', CategoryGroup::Ceramics, ['Plates & bowls', 'plates-and-bowls']],
            'wazony-i-patery' => ['Wazony i patery', CategoryGroup::Ceramics, ['Vases & platters', 'vases-and-platters']],
            'kadzielnice-i-podstawki' => ['Kadzielnice i podstawki', CategoryGroup::Ceramics, ['Incense holders & coasters', 'incense-holders-and-coasters']],
            'bizuteria' => ['Biżuteria', CategoryGroup::Ceramics, ['Jewellery', 'jewellery']],
            'jedwab' => ['Jedwab', CategoryGroup::Crafts, ['Silk', 'silk']],
            'kosmetyczki-i-piorniki' => ['Kosmetyczki i piórniki', CategoryGroup::Crafts, ['Pouches & pencil cases', 'pouches-and-pencil-cases']],
            'vouchery' => ['Vouchery', CategoryGroup::Workshops, null],
        ] as $slug => [$name, $group, $english]) {
            $this->old[$slug] = Category::factory()->create(['slug' => $slug, 'name' => $name, 'group' => $group, 'sort_order' => count($this->old) + 1]);

            if ($english !== null) {
                $this->old[$slug]->translations()->create(['locale' => 'en', 'name' => $english[0], 'slug' => $english[1]]);
            }
        }

        $this->old['kubki-i-filizanki']->update(['seo_title' => 'Kubki ręcznie robione z Krakowa', 'intro' => 'Każdy kubek toczę sama.']);

        foreach ([
            'zestaw-4-filizanek' => 'kubki-i-filizanki',
            'kubki-z-cytatem' => 'kubki-i-filizanki',
            'kubki-malowane' => 'kubki-i-filizanki',
            'podstawki-pod-bizuterie' => 'kadzielnice-i-podstawki',
            'kadzielnice' => 'kadzielnice-i-podstawki',
            'scrunchies' => 'jedwab',
            'jedwabne-opaski' => 'jedwab',
            'piorniki' => 'kosmetyczki-i-piorniki',
            // Kasia already moved the bowls next to the vases in the panel: they stay there.
            'miski' => 'wazony-i-patery',
            'voucher-kwotowy' => 'vouchery',
        ] as $slug => $category) {
            $product = Product::factory()->create(['slug' => $slug, 'category_id' => $this->old[$category]->id, 'is_published' => true]);
            ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => 7900, 'stock' => 2]);
        }
    }

    public function test_the_flat_categories_become_the_tree_and_keep_their_rows_and_texts(): void
    {
        $this->migrate();

        $this->assertSame(30, Category::count());

        $mugs = $this->category('kubki');
        $this->assertSame($this->old['kubki-i-filizanki']->id, $mugs->id);
        $this->assertSame(['Kubki', 'Kubki ręcznie robione z Krakowa', 'Każdy kubek toczę sama.', $this->category('glina')->id], [$mugs->name, $mugs->seo_title, $mugs->intro, $mugs->parent_id]);
        $this->assertSame(['Mugs', 'mugs'], [$mugs->translation('en')->name, $mugs->translation('en')->slug]);

        // „Jedwab” becomes Szycie: the top of the sewn branch.
        $sewing = $this->category('szycie');
        $this->assertSame($this->old['jedwab']->id, $sewing->id);
        $this->assertSame([null, CategoryGroup::Crafts, 'textiles'], [$sewing->parent_id, $sewing->group, $sewing->translation('en')->slug]);

        $this->assertSame($this->category('bizuteria')->id, $this->category('pierscionki')->parent_id);
        $this->assertSame(CategoryGroup::Ceramics, $this->category('rzezby')->group);
        $this->assertSame(CategoryGroup::Crafts, $this->category('etui-na-klucze')->group);
        $this->assertFalse($this->category('migotki')->hasTranslation('en'));
        $this->assertSame([1, 2, 3], [$this->category('glina')->sort_order, $sewing->sort_order, $this->category('vouchery')->sort_order]);
        $this->assertNull($this->category('vouchery')->parent_id);
        $this->assertSame(['kubki-z-wlasnym-napisem', 'kubki', 'filizanki'], Category::query()->where('parent_id', $this->category('glina')->id)->orderBy('sort_order')->limit(3)->pluck('slug')->all());
    }

    public function test_products_from_a_split_category_move_to_their_own_shelf(): void
    {
        $this->migrate();

        $shelf = fn (string $product) => Product::query()->where('slug', $product)->first()->category->slug;

        $this->assertSame('filizanki', $shelf('zestaw-4-filizanek'));
        $this->assertSame('kubki', $shelf('kubki-malowane'));
        $this->assertSame('kubki', $shelf('kubki-z-cytatem'));
        $this->assertSame('bizuteria', $shelf('podstawki-pod-bizuterie'));
        $this->assertSame('kadzielnice', $shelf('kadzielnice'));
        $this->assertSame('scrunchie', $shelf('scrunchies'));
        $this->assertSame('opaski', $shelf('jedwabne-opaski'));
        $this->assertSame('piorniki', $shelf('piorniki'));
        $this->assertSame('wazony', $shelf('miski'));
        $this->assertSame('vouchery', $shelf('voucher-kwotowy'));

        $this->assertSame(['kadzielnice'], Product::query()->where('slug', 'podstawki-pod-bizuterie')->first()->alsoInCategories->pluck('slug')->all());
        $this->assertSame(['kubki-z-wlasnym-napisem'], Product::query()->where('slug', 'kubki-z-cytatem')->first()->alsoInCategories->pluck('slug')->all());
    }

    public function test_the_old_addresses_lead_to_the_new_shelves(): void
    {
        $this->migrate();

        $this->get('/sklep/kubki-i-filizanki')->assertStatus(301)->assertRedirect(url('/sklep/kubki'));
        $this->get('/sklep/jedwab')->assertStatus(301)->assertRedirect(url('/sklep/szycie'));
        $this->get('/en/shop/mugs-and-cups')->assertStatus(301)->assertRedirect(url('/en/shop/mugs'));
        // An address that did not change needs no redirect.
        $this->get('/sklep/bizuteria')->assertOk();
        $this->get('/sklep/glina')->assertOk()->assertSee('Kubki');
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->migrate();
        $before = Category::query()->orderBy('id')->get(['id', 'slug', 'parent_id', 'sort_order'])->toArray();

        $this->migrate();

        $this->assertSame($before, Category::query()->orderBy('id')->get(['id', 'slug', 'parent_id', 'sort_order'])->toArray());
    }

    public function test_a_fresh_database_is_left_to_the_seeder(): void
    {
        Product::query()->delete();
        Category::query()->delete();

        $this->migrate();

        $this->assertSame(0, Category::count());
    }

    private function migrate(): void
    {
        /** @var Migration $migration */
        $migration = require base_path('app/Modules/Catalog/Database/Migrations/2026_11_01_230100_arrange_categories_under_clay_and_sewing.php');
        $migration->up();
    }

    private function category(string $slug): Category
    {
        return Category::query()->where('slug', $slug)->firstOrFail();
    }
}
