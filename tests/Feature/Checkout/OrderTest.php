<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_order_waits_for_payment(): void
    {
        $order = Order::factory()->create()->fresh();

        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
    }

    public function test_item_keeps_its_copy_after_the_product_changes(): void
    {
        $variant = ProductVariant::factory()->create(['label' => '300 ml', 'price_gross' => 7900]);
        $item = OrderItem::factory()->create([
            'product_variant_id' => $variant->id,
            'product_name' => 'Kubki malowane ręcznie',
            'variant_label' => '300 ml',
            'unit_price_gross' => 7900,
            'quantity' => 2,
        ]);

        $variant->update(['label' => '320 ml', 'price_gross' => 8900]);
        $variant->product->delete();

        $item->refresh();
        $this->assertNull($item->product_variant_id);
        $this->assertSame('300 ml', $item->variant_label);
        $this->assertSame(15800, $item->lineTotal());
    }
}
