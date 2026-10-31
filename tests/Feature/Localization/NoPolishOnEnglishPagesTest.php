<?php

namespace Tests\Feature\Localization;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * MA-301: no page under /en/ shows Polish text. Every English page the router knows is opened and what a visitor sees
 * or hears — the text, alt, aria-label, title and placeholder — is searched for Polish letters. Kraków is a name, and a
 * link marked lang="pl" (the switch to the Polish site) speaks Polish on purpose.
 */
class NoPolishOnEnglishPagesTest extends TestCase
{
    use RefreshDatabase;

    /** English pages that need a value the router cannot make up; a new one fails the test until it is listed. */
    private const PARAMETERS = [
        'en.shop.category' => ['category' => 'ceramics'],
        'en.product.show' => ['product' => 'rough-edged-bowl'],
    ];

    /** English addresses with nothing to show without a token from an e-mail. */
    private const SKIPPED = ['en.stock-notifications.destroy'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_no_english_page_shows_polish_text(): void
    {
        $category = Category::factory()->create(['name' => 'Ceramika', 'slug' => 'ceramika']);
        $category->translations()->create(['locale' => 'en', 'name' => 'Ceramics', 'slug' => 'ceramics']);
        $product = Product::factory()->create(['name' => 'Miska z surową krawędzią', 'slug' => 'miska-z-surowa-krawedzia', 'category_id' => $category->id]);
        $product->translations()->create(['locale' => 'en', 'name' => 'Rough-edged bowl', 'slug' => 'rough-edged-bowl']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Mała', 'stock' => 2]);
        $variant->translations()->create(['locale' => 'en', 'label' => 'Small']);
        $variant->prices()->create(['currency' => 'EUR', 'amount_minor' => 2900]);

        $checked = [];

        foreach (Router::getRoutes() as $route) {
            /** @var Route $route */
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'en.') || ! in_array('GET', $route->methods(), true) || in_array($name, self::SKIPPED, true)) {
                continue;
            }

            $parameters = self::PARAMETERS[$name] ?? [];
            $this->assertSame([], array_diff($route->parameterNames(), array_keys($parameters)), $name.' needs its parameters listed in this test');

            $response = $this->get(route($name, $parameters));

            if ($response->isRedirect()) {
                // An empty basket sends the checkout back to the shop; the shop is checked on its own.
                continue;
            }

            $response->assertOk();
            $this->assertSame([], $this->polishIn($response->getContent()), $name.' shows Polish text');
            $checked[] = $name;
        }

        // The pages a visitor lands on most must have been opened, not skipped.
        foreach (['en.home', 'en.shop.index', 'en.shop.category', 'en.product.show', 'en.mug.index', 'en.consent.edit'] as $page) {
            $this->assertContains($page, $checked);
        }
    }

    public function test_an_english_address_without_a_page_gets_an_english_error_page(): void
    {
        $this->get('/en/no-such-page')
            ->assertNotFound()
            ->assertSee('<html lang="en"', false)
            ->assertSee('This page is not here')
            ->assertSee('href="'.url('/en/shop').'"', false)
            ->assertDontSee('Tej strony tu nie ma');

        $this->assertSame([], $this->polishIn($this->get('/en/no-such-page')->getContent()));

        $this->get('/nie-ma-takiej-strony')
            ->assertNotFound()
            ->assertSee('<html lang="pl"', false)
            ->assertSee('Tej strony tu nie ma');
    }

    public function test_the_cookie_banner_and_the_basket_messages_speak_english(): void
    {
        $this->get('/en/shop')
            ->assertSee('The basket and the forms run on essential cookies.')
            ->assertSee('>Allow statistics</button>', false)
            ->assertSee('data-failure="Something went wrong on my side.', false)
            ->assertSee('data-favorite-removed="Removed from saved"', false)
            ->assertDontSee('Zgadzam się na statystyki');

        $this->get('/sklep')
            ->assertSee('>Zgadzam się na statystyki</button>', false)
            ->assertSee('data-favorite-removed="Usunięte z ulubionych"', false);
    }

    /** @return list<string> the pieces of visible text that hold Polish letters */
    private function polishIn(string $html): array
    {
        // Scripts and styles are not read; structured data names the shop's address in Polish on purpose.
        $html = preg_replace('#<(script|style|template)\b[^>]*>.*?</\1>#si', ' ', $html);
        // A link marked as Polish, such as the language switch, speaks Polish on purpose.
        $html = preg_replace('#<(a|span|p)\b[^>]*\blang="pl"[^>]*>.*?</\1>#si', ' ', $html);

        preg_match_all('#\b(?:alt|aria-label|title|placeholder)="([^"]*)"#i', $html, $attributes);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $pieces = [...$attributes[1], ...preg_split('/\n+/', $text)];

        return array_values(array_unique(array_filter(
            array_map(fn (string $piece) => trim(preg_replace('/\s+/', ' ', $piece)), $pieces),
            fn (string $piece) => preg_match('/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u', str_ireplace('kraków', '', $piece)) === 1,
        )));
    }
}
