<?php

namespace App\Modules\Gifts\Database\Factories;

use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\VoucherCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => VoucherCode::generate(),
            'order_item_id' => null,
            'recipient_name' => fake()->firstName(),
            'dedication' => fake()->sentence(),
            'valid_until' => now()->addYear()->toDateString(),
            'redeemed_at' => null,
        ];
    }
}
