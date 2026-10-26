<?php

namespace App\Modules\Gifts\Database\Factories;

use App\Modules\Gifts\Models\Bundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bundle>
 */
class BundleFactory extends Factory
{
    protected $model = Bundle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'discount_percent' => 10,
            'is_published' => true,
            'sort_order' => 0,
        ];
    }
}
