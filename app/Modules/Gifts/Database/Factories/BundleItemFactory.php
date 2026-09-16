<?php

namespace App\Modules\Gifts\Database\Factories;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Gifts\Models\Bundle;
use App\Modules\Gifts\Models\BundleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BundleItem>
 */
class BundleItemFactory extends Factory
{
    protected $model = BundleItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bundle_id' => Bundle::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'sort_order' => 0,
        ];
    }
}
