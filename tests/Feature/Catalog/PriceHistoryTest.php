<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_price_is_recorded(): void
    {
        $variant = ProductVariant::factory()->create(['price_gross' => 9900]);

        $this->assertSame([9900], $variant->priceHistory()->pluck('price_gross')->all());
    }

    public function test_price_change_adds_a_row_and_other_changes_do_not(): void
    {
        $variant = ProductVariant::factory()->create(['price_gross' => 9900]);

        $variant->update(['stock' => 1]);
        $variant->update(['price_gross' => 7900]);

        $this->assertSame([9900, 7900], $variant->priceHistory()->orderBy('id')->pluck('price_gross')->all());
    }

    public function test_lowest_price_from_30_days_before_discount(): void
    {
        $this->travelTo(now()->subDays(60));
        $variant = ProductVariant::factory()->create(['price_gross' => 9900]);

        $this->travelBack();
        $this->travelTo(now()->subDays(20));
        $variant->update(['price_gross' => 8900]);

        $this->travelBack();
        $this->travelTo(now()->subDays(10));
        $variant->update(['price_gross' => 9900]);

        $this->travelBack();
        $discountStart = now();
        $variant->update(['price_gross' => 7900, 'compare_at_price' => 9900]);

        $this->assertSame(8900, $variant->lowestPriceBefore($discountStart));
    }

    public function test_price_in_force_when_window_opens_counts(): void
    {
        $this->travelTo(now()->subDays(45));
        $variant = ProductVariant::factory()->create(['price_gross' => 9900]);
        $this->travelBack();

        $this->assertSame(9900, $variant->lowestPriceBefore(now()));
    }

    public function test_price_older_than_window_but_replaced_does_not_count(): void
    {
        $this->travelTo(now()->subDays(45));
        $variant = ProductVariant::factory()->create(['price_gross' => 5000]);
        $this->travelBack();

        $this->travelTo(now()->subDays(40));
        $variant->update(['price_gross' => 9900]);
        $this->travelBack();

        $this->assertSame(9900, $variant->lowestPriceBefore(now()));
    }
}
