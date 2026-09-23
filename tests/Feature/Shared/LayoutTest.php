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
}
