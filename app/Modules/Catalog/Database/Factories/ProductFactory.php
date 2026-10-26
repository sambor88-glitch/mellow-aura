<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'name' => fake()->words(3, true),
            'category_id' => Category::factory(),
            'description' => fake()->paragraph(),
            'is_published' => true,
            'is_one_off' => false,
            'sort_order' => 0,
            'stamp_enabled' => false,
            'occasions' => [],
            'recipients' => [],
        ];
    }
}
