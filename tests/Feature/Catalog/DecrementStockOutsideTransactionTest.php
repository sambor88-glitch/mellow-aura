<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\DecrementStock;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use LogicException;
use Tests\TestCase;

/**
 * Separate class: RefreshDatabase wraps every test in a transaction,
 * which would hide the missing one.
 */
class DecrementStockOutsideTransactionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_refuses_to_run_outside_a_transaction(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 1]);

        try {
            app(DecrementStock::class)->handle([$variant->id => 1]);
            $this->fail('Expected LogicException.');
        } catch (LogicException) {
        }

        $this->assertSame(1, $variant->fresh()->stock);
    }
}
