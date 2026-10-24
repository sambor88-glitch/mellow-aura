<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'label' => fake()->word(),
            'price_gross' => fake()->numberBetween(50, 400) * 100,
            'compare_at_price' => null,
            'stock' => 3,
            'sort_order' => 0,
        ];
    }
}
