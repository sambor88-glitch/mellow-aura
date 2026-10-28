<?php

namespace Tests\Feature\Shared;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_keyboard_can_skip_the_menu_straight_to_the_page(): void
    {
        $html = $this->get('/sklep')->assertOk()->getContent();

        // The skip link comes before the header's links and leads to the main part of the page.
        $this->assertMatchesRegularExpression('#<body>\s*(<!--.*?-->\s*)?<a href="\#tresc"[^>]*>Przejdź do treści</a>#s', preg_replace('/\{\{--.*?--\}\}/s', '', $html));
        $this->assertStringContainsString('<main id="tresc" tabindex="-1"', $html);
    }

    public function test_the_firing_page_is_in_the_polish_menu_and_not_in_the_english_one(): void
    {
        $menu = fn (string $page) => preg_match('#<nav aria-label="(Główne menu|Main menu)".*?</nav>#s', $this->get($page)->assertOk()->getContent(), $nav) ? $nav[0] : '';

        // Between the workshops and „O mnie”, the order of the menu in the header.
        $this->assertMatchesRegularExpression('#>Warsztaty</a>.*<a href="'.preg_quote(route('firing.index'), '#').'"[^>]*>Wypał</a>.*>O mnie</a>#s', $menu('/sklep'));
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('firing.index'), '#').'"\s+aria-current="page"#', $menu('/wypal-ceramiki-krakow'));

        // Firing is Polish-only, so the English menu leaves it out.
        $english = $menu('/en/shop');
        $this->assertStringContainsString('>Shop</a>', $english);
        $this->assertStringNotContainsString('wypal-ceramiki-krakow', $english);
    }
}
