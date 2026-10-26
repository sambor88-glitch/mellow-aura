<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_variant_records_its_starting_price(): void
    {
        $variant = ProductVariant::factory()->create(['price_gross' => 14900]);

        $this->assertSame([14900], $variant->priceHistory()->pluck('price_gross')->all());
    }

    public function test_a_price_change_adds_a_history_row(): void
    {
        $variant = ProductVariant::factory()->create(['price_gross' => 14900]);

        $variant->update(['price_gross' => 12900]);

        $this->assertSame([14900, 12900], $variant->priceHistory()->orderBy('id')->pluck('price_gross')->all());
    }

    public function test_saving_other_fields_adds_no_history_row(): void
    {
        $variant = ProductVariant::factory()->create(['price_gross' => 14900, 'stock' => 3]);

        $variant->update(['stock' => 2]);
        $variant->save();

        $this->assertSame(1, $variant->priceHistory()->count());
    }
}
