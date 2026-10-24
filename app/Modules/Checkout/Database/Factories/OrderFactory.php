<?php

namespace App\Modules\Checkout\Database\Factories;

use App\Modules\Checkout\Enums\ShippingMethod;
use App\Modules\Checkout\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number' => 'MA-'.now()->year.'-'.fake()->unique()->numberBetween(1000, 9999),
            'email' => fake()->safeEmail(),
            'name' => fake()->name(),
            'shipping_method' => ShippingMethod::ParcelLocker,
            'locker_code' => 'KRA01M',
            'shipping_gross' => 1600,
            'total_gross' => 10000,
        ];
    }
}
