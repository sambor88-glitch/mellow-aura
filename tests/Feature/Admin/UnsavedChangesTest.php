<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every panel screen watches its forms for unsaved changes (MA-251). The behaviour itself runs in the browser:
 * tests/e2e/panel.spec.js changes a card in „Ustawienia”, saves another and follows a menu link.
 */
class UnsavedChangesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_long_screens_watch_their_forms_and_name_the_screen(): void
    {
        $owner = User::factory()->create();

        foreach (['/panel/ustawienia' => 'Ustawienia', '/panel/kategorie' => 'Kategorie', '/panel/tresci' => 'Treści', '/panel/produkty' => 'Produkty'] as $page => $title) {
            $this->actingAs($owner)->get($page)
                ->assertOk()
                ->assertSee('x-data="unsavedChanges(', false)
                ->assertSee('Masz niezapisane zmiany w „:form”. Zapisz je najpierw albo porzuć.', false)
                ->assertSee('\\u0022screen\\u0022:\\u0022'.$title.'\\u0022', false)
                ->assertSee('x-ref="unsavedTemplate"', false)
                ->assertSee('data-drop', false);
        }
    }

    public function test_the_login_page_has_nothing_to_watch(): void
    {
        $this->get('/panel/logowanie')->assertOk()->assertDontSee('unsavedChanges(', false);
    }
}
