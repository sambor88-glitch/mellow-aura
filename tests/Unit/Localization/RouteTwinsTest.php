<?php

namespace Tests\Unit\Localization;

use App\Modules\Localization\Routing\RouteTwins;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Tests\TestCase;

class RouteTwinsTest extends TestCase
{
    public function test_with_english_switched_off_no_english_address_exists(): void
    {
        config(['localization.enabled' => ['pl']]);

        $router = $this->routerWithShop();
        RouteTwins::register($router);

        $this->assertNull($router->getRoutes()->getByName('en.shop.index'));
        $this->assertCount(2, $router->getRoutes());
    }

    public function test_a_twin_copies_the_page_under_its_english_address(): void
    {
        config(['localization.enabled' => ['pl', 'en']]);

        $router = $this->routerWithShop();
        RouteTwins::register($router);

        $twin = $router->getRoutes()->getByName('en.shop.category');
        $this->assertSame('en/shop/{category}', $twin->uri());
        $this->assertSame(['category' => 'slug'], $twin->bindingFields());
        $this->assertSame('en', $twin->getAction('locale'));
        $this->assertSame(['web'], $twin->middleware());
        $this->assertSame('ShopController@__invoke', $twin->getActionName());
        $this->assertSame(['category' => '[a-z-]+'], $twin->wheres);

        $this->assertSame('en/shop', $router->getRoutes()->getByName('en.shop.index')->uri());
    }

    public function test_a_listed_page_that_is_not_built_yet_is_skipped(): void
    {
        config(['localization.enabled' => ['pl', 'en']]);

        $router = $this->routerWithShop();
        RouteTwins::register($router);

        $this->assertNull($router->getRoutes()->getByName('en.journal.index'));
    }

    private function routerWithShop(): Router
    {
        $router = new Router(new Dispatcher, $this->app);
        $router->middleware('web')->group(function (Router $router) {
            $router->get('/sklep', 'ShopController@__invoke')->name('shop.index');
            $router->get('/sklep/{category:slug}', 'ShopController@__invoke')->where('category', '[a-z-]+')->name('shop.category');
        });

        return $router;
    }
}
