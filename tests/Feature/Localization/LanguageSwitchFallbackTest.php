<?php

namespace Tests\Feature\Localization;

use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

class LanguageSwitchFallbackTest extends TestCase
{
    use RefreshDatabase;

    /** Addresses for machines, not people: nobody switches their language. */
    private const NOT_PAGES = ['sitemap', 'merchant-feed', 'catalog-feed'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_every_public_page_without_an_english_twin_has_an_english_page_to_switch_to(): void
    {
        $twins = array_keys(config('localization.paths.en'));
        $fallbacks = config('localization.fallbacks');

        $polishOnly = collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('GET', $route->methods(), true)
                && str_starts_with($route->getActionName(), 'App\\Modules\\')
                && ($name = $route->getName()) !== null
                && ! str_starts_with($name, 'admin.')
                && ! str_starts_with($name, 'en.')
                && ! in_array($name, self::NOT_PAGES, true)
                && ! in_array($name, $twins, true))
            ->map(fn (Route $route) => $route->getName())
            ->values();

        $this->assertNotEmpty($polishOnly);

        foreach ($polishOnly as $name) {
            // Without one the switch leads to the home page, which Google reads as a soft 404.
            $this->assertArrayHasKey($name, $fallbacks, "{$name} has no page for the language switch to lead to.");
            $this->assertTrue(Locales::has($fallbacks[$name], 'en'), "{$name} falls back to {$fallbacks[$name]}, which has no English version.");
        }
    }

    public function test_from_the_gift_choice_page_the_switch_leads_to_the_shop_not_the_home_page(): void
    {
        $this->get('/wybierz-kubek')
            ->assertOk()
            ->assertSee('href="'.url('/en/shop').'?unavailable=1" hreflang="en"', false)
            ->assertDontSee('href="'.url('/en').'?unavailable=1"', false);
    }
}
