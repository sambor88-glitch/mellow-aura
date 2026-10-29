<?php

namespace Tests\Feature\Checkout;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Items saved before MA-191 take the currency of their order.
 */
class OrderItemCurrencyMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'app/Modules/Checkout/Database/Migrations/2026_10_26_110000_add_currency_to_order_items_table.php';

    public function test_items_already_saved_take_the_currency_of_their_order(): void
    {
        $migration = require base_path(self::MIGRATION);
        $migration->down();

        $zloty = $this->order('PLN');
        $euro = $this->order('EUR');
        foreach ([$zloty, $euro] as $order) {
            DB::table('order_items')->insert(['order_id' => $order->id, 'product_name' => 'Wazony', 'variant_label' => 'Niski 16 cm', 'quantity' => 1, 'unit_price_gross' => 3900, 'created_at' => now(), 'updated_at' => now()]);
        }

        $migration->up();

        $this->assertSame('PLN', $zloty->items()->value('currency'));
        $this->assertSame('EUR', $euro->items()->value('currency'));
    }

    private function order(string $currency): Order
    {
        return Order::create([
            'status' => OrderStatus::New,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
            'shipping_gross' => 0,
            'total_gross' => 3900,
            'currency' => $currency,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Pending,
        ]);
    }
}
