<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\PriceHistory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_it_seeds_the_offer_from_the_prototype(): void
    {
        $this->seed(CatalogSeeder::class);

        // Glina and Szycie with everything under them (MA-351), and the vouchers outside the tree.
        $this->assertSame(30, Category::count());
        $this->assertSame(17, Product::count());
        $this->assertSame(13, Product::where('is_published', true)->count());
        $this->assertSame(49, ProductVariant::count());
        $this->assertSame(49, PriceHistory::count());
        $this->assertSame(0, ProductVariant::whereNotNull('compare_at_price')->count());
    }

    public function test_it_attaches_photos_and_leaves_the_originals_in_place(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertSame(21, Media::count());
        $this->assertSame('Kubek malowany ręcznie', Product::where('slug', 'kubki-malowane')->firstOrFail()->getFirstMedia('images')->getCustomProperty('alt'));
        $this->assertFileExists(base_path('zdjecia/kubek-cappuccino.webp'));
    }

    public function test_running_it_again_restores_a_photo_whose_file_is_missing(): void
    {
        $this->seed(CatalogSeeder::class);
        $lost = Product::where('slug', 'wazony')->firstOrFail()->getFirstMedia('images');
        Storage::disk('public')->delete($lost->getPathRelativeToRoot());

        $this->seed(CatalogSeeder::class);

        $restored = Product::where('slug', 'wazony')->firstOrFail()->getFirstMedia('images');
        $this->assertTrue(Storage::disk('public')->exists($restored->getPathRelativeToRoot()));
        $this->assertSame(21, Media::count());
    }

    public function test_vouchers_are_not_stock_tracked_and_quote_mugs_are_one_offs(): void
    {
        $this->seed(CatalogSeeder::class);

        $voucherStock = ProductVariant::whereHas(
            'product.category',
            fn ($query) => $query->where('group', CategoryGroup::Workshops),
        )->pluck('stock');

        $this->assertCount(8, $voucherStock);
        $this->assertTrue($voucherStock->every(fn ($stock) => $stock === null));

        $quoteMugs = Product::where('slug', 'kubki-z-cytatem')->firstOrFail();

        // One piece of each lettering; a text of your own is ordered in the mug configurator (MA-277).
        $this->assertSame([1, 1, 1, 1], $quoteMugs->variants()->orderBy('id')->pluck('stock')->all());
    }

    public function test_running_it_again_keeps_changes_made_in_the_panel(): void
    {
        $this->seed(CatalogSeeder::class);

        $variant = Product::where('slug', 'wazony')->firstOrFail()->variants()->where('label', 'Niski 16 cm')->firstOrFail();
        $variant->update(['price_gross' => 25900]);

        $this->seed(CatalogSeeder::class);

        $this->assertSame(25900, $variant->fresh()->price_gross);
        $this->assertSame(17, Product::count());
        $this->assertSame(49, ProductVariant::count());
        $this->assertSame(21, Media::count());
    }

    public function test_categories_get_their_english_names_except_the_polish_only_vouchers(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertSame('Mugs', Category::query()->where('slug', 'kubki')->first()->translation('en')->name);
        $this->assertSame('textiles', Category::query()->where('slug', 'szycie')->first()->translation('en')->slug);
        $this->assertFalse(Category::query()->where('slug', 'vouchery')->first()->hasTranslation('en'));
        // Without an English name Kasia gives it in the panel: not on /en/.
        $this->assertFalse(Category::query()->where('slug', 'migotki')->first()->hasTranslation('en'));
    }

    public function test_the_categories_stand_in_a_tree_under_glina_and_szycie(): void
    {
        $this->seed(CatalogSeeder::class);

        $category = fn (string $slug) => Category::query()->where('slug', $slug)->first();

        $this->assertNull($category('glina')->parent_id);
        $this->assertNull($category('szycie')->parent_id);
        $this->assertNull($category('vouchery')->parent_id);
        $this->assertSame($category('glina')->id, $category('bizuteria')->parent_id);
        $this->assertSame($category('bizuteria')->id, $category('kolczyki')->parent_id);
        $this->assertSame($category('szycie')->id, $category('etui-na-klucze')->parent_id);
        // The group comes from the top of the branch: the care rule of ceramics or of sewn things.
        $this->assertSame(CategoryGroup::Ceramics, $category('pierscionki')->group);
        $this->assertSame(CategoryGroup::Crafts, $category('scrunchie')->group);
        $this->assertSame(['kubki-z-wlasnym-napisem', 'kubki', 'filizanki'], Category::query()->where('parent_id', $category('glina')->id)->orderBy('sort_order')->limit(3)->pluck('slug')->all());

        // Maciej, 1.11.2026: the jewellery stands come with the jewellery and also among the incense holders.
        $stands = Product::query()->where('slug', 'podstawki-pod-bizuterie')->first();
        $this->assertSame('bizuteria', $stands->category->slug);
        $this->assertSame(['kadzielnice'], $stands->alsoInCategories->pluck('slug')->all());
        $this->assertSame('scrunchie', Product::query()->where('slug', 'scrunchies')->first()->category->slug);
    }
}
