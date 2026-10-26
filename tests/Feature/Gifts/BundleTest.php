<?php

namespace Tests\Feature\Gifts;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Gifts\Cart\BundleLine;
use App\Modules\Gifts\Models\Bundle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_price_is_the_sum_of_the_parts_minus_the_discount_in_whole_zloty(): void
    {
        $this->assertSame(13300, Bundle::discounted(14800, 10));
        $this->assertSame(11300, Bundle::discounted(12500, 10));
        $this->assertSame(14800, Bundle::discounted(14800, 0));
    }

    public function test_the_shares_of_the_parts_add_up_to_the_price_of_the_set(): void
    {
        $bundle = $this->bundle([7900, 6900, 4999], discount: 15);

        $shares = $bundle->partPrices();

        $this->assertSame(19799, $bundle->fullPrice());
        $this->assertSame(16800, $bundle->price());
        $this->assertCount(3, $shares);
        $this->assertSame(16800, array_sum($shares));
        $this->assertSame([6703, 5854], array_slice($shares, 0, 2));
    }

    public function test_a_part_with_a_feature_to_accept_brings_it_to_the_set_and_its_order_item(): void
    {
        $bundle = $this->bundle([7900, 6900]);
        $bundle->items[1]->variant->product->update(['name' => 'Talerz ze złotem', 'deviation' => 'Nie do zmywarki']);
        $line = new BundleLine(BundleLine::keyFor($bundle), 1, $bundle->load('items.variant.product'));

        $this->assertSame(['Talerz ze złotem' => 'Nie do zmywarki'], $line->deviations());
        $this->assertSame([null, 'Nie do zmywarki'], array_map(fn ($item) => $item->deviation, $line->orderItems()));
    }

    public function test_a_set_shows_only_when_it_is_published_and_every_part_is_on_sale(): void
    {
        $live = $this->bundle([7900, 6900]);
        $hidden = $this->bundle([7900, 6900], published: false);
        $soldOut = $this->bundle([7900, 6900]);
        $soldOut->items[0]->variant->update(['stock' => 0]);
        $unpublished = $this->bundle([7900, 6900]);
        $unpublished->items[1]->variant->product->update(['is_published' => false]);
        $single = $this->bundle([7900]);
        $madeToOrder = $this->bundle([7900, 6900]);
        $madeToOrder->items[0]->variant->update(['stock' => null]);

        $this->assertEqualsCanonicalizing([$live->id, $madeToOrder->id], Bundle::query()->live()->pluck('id')->all());
    }

    /**
     * @param  list<int>  $prices
     */
    private function bundle(array $prices, int $discount = 10, bool $published = true): Bundle
    {
        $bundle = Bundle::factory()->create(['discount_percent' => $discount, 'is_published' => $published]);

        foreach ($prices as $index => $price) {
            $variant = ProductVariant::factory()->for(Product::factory())->create(['price_gross' => $price, 'stock' => 3]);
            $bundle->items()->create(['product_variant_id' => $variant->id, 'sort_order' => $index]);
        }

        return $bundle->load('items.variant.product');
    }
}
