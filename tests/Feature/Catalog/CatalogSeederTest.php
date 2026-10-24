<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Enums\Occasion;
use App\Modules\Catalog\Enums\Recipient;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\PriceHistory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_seeds_the_offer_from_the_prototype(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertSame(8, Category::query()->count());
        $this->assertSame(17, Product::query()->count());
        $this->assertSame(13, Product::query()->published()->count());
        $this->assertSame(ProductVariant::query()->count(), PriceHistory::query()->count());
    }

    public function test_product_details_match_the_prototype(): void
    {
        $this->seed(CatalogSeeder::class);

        $vases = Product::query()->where('slug', 'wazony')->with('variants', 'category')->firstOrFail();
        $tallVase = $vases->variants->firstWhere('label', 'Wysoki 24 cm');

        $this->assertSame('Wazony i patery', $vases->category->name);
        $this->assertSame(29900, $tallVase->price_gross);
        $this->assertSame(31500, $tallVase->compare_at_price);
        $this->assertTrue($vases->occasions->contains(Occasion::Wedding));
        $this->assertTrue($vases->recipients->contains(Recipient::ForHer));
        $this->assertCount(1, $vases->getMedia(Product::GALLERY));

        $mugs = Product::query()->where('slug', 'kubki-z-cytatem')->firstOrFail();
        $this->assertTrue($mugs->is_one_off);
        $this->assertSame([1], $mugs->variants->pluck('stock')->unique()->values()->all());
    }

    public function test_drafts_have_no_photos(): void
    {
        $this->seed(CatalogSeeder::class);

        Product::query()->where('is_published', false)->get()
            ->each(fn (Product $product) => $this->assertCount(0, $product->getMedia(Product::GALLERY)));
    }

    public function test_running_twice_keeps_panel_changes(): void
    {
        $this->seed(CatalogSeeder::class);
        Product::query()->where('slug', 'patery')->update(['name' => 'Patery czarne']);

        $this->seed(CatalogSeeder::class);

        $this->assertSame(17, Product::query()->count());
        $this->assertSame('Patery czarne', Product::query()->where('slug', 'patery')->value('name'));
    }
}
