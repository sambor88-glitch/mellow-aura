<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\DecrementStock;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DecrementStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_takes_pieces_off_the_shelf_and_reports_what_was_missing(): void
    {
        $vase = ProductVariant::factory()->create(['stock' => 3]);
        $voucher = ProductVariant::factory()->create(['stock' => null]);
        $decrementStock = app(DecrementStock::class);

        $missing = DB::transaction(fn () => [
            $decrementStock($vase->id, 2),
            $decrementStock($vase->id, 2),
            $decrementStock($voucher->id, 5),
            $decrementStock($voucher->id + 1000, 4),
        ]);

        $this->assertSame([0, 1, 0, 4], $missing);
        $this->assertSame(0, $vase->fresh()->stock);
        $this->assertNull($voucher->fresh()->stock);
    }
}
