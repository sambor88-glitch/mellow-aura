<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Settings\Actions\SaveSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopShortcutsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->put('/panel/kategorie/skroty')->assertRedirect('/panel/logowanie');
    }

    public function test_a_line_from_the_panel_shows_under_its_card_in_its_language(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/panel/kategorie')
            ->assertOk()
            ->assertSee('Skróty nad sklepem')
            ->assertSee('name="pl[mug]"', false)
            ->assertSee('name="en[mug]"', false);

        $this->put('/panel/kategorie/skroty', [
            'pl' => ['mug' => '  Twój tekst, Twój kolor  ', 'bundles' => ''],
            'en' => ['mug' => 'Your words, your colour'],
        ])->assertRedirect('/panel/kategorie#skroty');

        $this->get('/sklep')
            ->assertOk()
            ->assertSee('Twój tekst, Twój kolor&nbsp;<span aria-hidden="true" class="text-lead">→</span>', false)
            ->assertSee('Zestawy prezentowe&nbsp;<span aria-hidden="true" class="font-normal text-lead">→</span>', false)
            ->assertDontSee('Your words, your colour');

        $this->get('/en/shop')
            ->assertOk()
            ->assertSee('Your words, your colour')
            ->assertDontSee('Twój tekst, Twój kolor');
    }

    public function test_english_lines_stay_when_the_form_shows_polish_only(): void
    {
        app(SaveSettings::class)(['shop_shortcut_note_mug_en' => 'Your words, your colour']);

        $this->actingAs(User::factory()->create())
            ->put('/panel/kategorie/skroty', ['pl' => ['mug' => 'Twój tekst']])
            ->assertRedirect('/panel/kategorie#skroty');

        $this->get('/en/shop')->assertSee('Your words, your colour');
    }

    public function test_a_line_too_long_comes_back_with_a_message(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/panel/kategorie')
            ->put('/panel/kategorie/skroty', ['pl' => ['mug' => str_repeat('a', 61)]])
            ->assertRedirect('/panel/kategorie#skroty')
            ->assertSessionHasErrors(['pl.mug' => 'Podpis zmieszczę do 60 znaków — wystarczy kilka słów'], errorBag: 'skroty');
    }
}
