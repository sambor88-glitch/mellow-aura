<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\PriceHistory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);

        $product->category->delete();
    }

    public function test_deleting_a_product_removes_its_variants_and_price_history(): void
    {
        $variant = ProductVariant::factory()->create();

        $variant->product->delete();

        $this->assertSame(0, ProductVariant::count());
        $this->assertSame(0, PriceHistory::count());
    }
}
