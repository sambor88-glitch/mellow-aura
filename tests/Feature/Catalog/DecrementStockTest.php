<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\DecrementStock;
use App\Modules\Catalog\Exceptions\InsufficientStock;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DecrementStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_takes_items_off_the_shelf(): void
    {
        $mug = ProductVariant::factory()->create(['stock' => 1]);
        $plate = ProductVariant::factory()->create(['stock' => 3]);

        DB::transaction(fn () => app(DecrementStock::class)->handle([$mug->id => 1, $plate->id => 2]));

        $this->assertSame(0, $mug->fresh()->stock);
        $this->assertSame(1, $plate->fresh()->stock);
    }

    public function test_changes_nothing_when_one_variant_is_short(): void
    {
        $mug = ProductVariant::factory()->create(['stock' => 1]);
        $plate = ProductVariant::factory()->create(['stock' => 3]);

        try {
            DB::transaction(fn () => app(DecrementStock::class)->handle([$plate->id => 2, $mug->id => 2]));
            $this->fail('Expected InsufficientStock.');
        } catch (InsufficientStock) {
        }

        $this->assertSame(1, $mug->fresh()->stock);
        $this->assertSame(3, $plate->fresh()->stock);
    }

    public function test_unknown_variant_counts_as_out_of_stock(): void
    {
        $this->expectException(InsufficientStock::class);

        DB::transaction(fn () => app(DecrementStock::class)->handle([999 => 1]));
    }
}
