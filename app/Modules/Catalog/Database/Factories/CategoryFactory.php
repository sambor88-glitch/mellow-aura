<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'group' => CategoryGroup::Ceramics,
            'sort_order' => 0,
        ];
    }
}
