<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'label' => fake()->randomElement(['Mała', 'Średnia', 'Duża']),
            'price_gross' => fake()->numberBetween(50, 400) * 100,
            'compare_at_price' => null,
            'stock' => fake()->numberBetween(0, 10),
        ];
    }
}
