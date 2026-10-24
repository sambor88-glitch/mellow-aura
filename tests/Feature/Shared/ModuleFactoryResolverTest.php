<?php

namespace Tests\Feature\Shared;

use App\Models\User;
use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Catalog\Models\Product;
use Database\Factories\UserFactory;
use Tests\TestCase;

class ModuleFactoryResolverTest extends TestCase
{
    public function test_module_models_use_factories_from_their_module(): void
    {
        $this->assertInstanceOf(ProductFactory::class, Product::factory());
    }

    public function test_app_models_keep_default_factories(): void
    {
        $this->assertInstanceOf(UserFactory::class, User::factory());
    }
}
