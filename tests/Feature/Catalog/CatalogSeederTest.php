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

        $this->assertSame(8, Category::count());
        $this->assertSame(17, Product::count());
        $this->assertSame(13, Product::where('is_published', true)->count());
        $this->assertSame(50, ProductVariant::count());
        $this->assertSame(50, PriceHistory::count());
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

        $this->assertSame([1, 1, 1, 1, null], $quoteMugs->variants()->orderBy('id')->pluck('stock')->all());
    }

    public function test_running_it_again_keeps_changes_made_in_the_panel(): void
    {
        $this->seed(CatalogSeeder::class);

        $variant = Product::where('slug', 'wazony')->firstOrFail()->variants()->where('label', 'Niski 16 cm')->firstOrFail();
        $variant->update(['price_gross' => 25900]);

        $this->seed(CatalogSeeder::class);

        $this->assertSame(25900, $variant->fresh()->price_gross);
        $this->assertSame(17, Product::count());
        $this->assertSame(50, ProductVariant::count());
        $this->assertSame(21, Media::count());
    }
}
