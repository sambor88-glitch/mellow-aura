<?php

namespace App\Modules\Checkout\Database\Factories;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_name' => fake()->words(2, true),
            'variant_label' => fake()->word(),
            'quantity' => 1,
            'unit_price_gross' => 7900,
        ];
    }
}
