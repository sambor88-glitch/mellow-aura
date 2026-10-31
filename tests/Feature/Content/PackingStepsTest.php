<?php

namespace Tests\Feature\Content;

use App\Models\User;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackingStepsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_shipping_page_lists_the_packing_steps_in_the_language_of_the_page(): void
    {
        $this->get('/wysylka-i-pielegnacja')
            ->assertOk()
            ->assertSee('Jak pakuję i wysyłam')
            ->assertSeeInOrder(['Każdą rzecz owijam osobno w papier pakowy.', 'dostajesz mailem numer do śledzenia.']);

        // The rest of the page is written in Polish, so it has no English address until MA-187 (MA-301);
        // the English packing steps wait in the panel for it.
        $this->get('/en/shipping-and-care')->assertNotFound();
    }

    public function test_the_panel_saves_both_texts(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/panel/tresci/teksty', ['text_packing_steps' => "Krok jeden\nKrok dwa", 'text_packing_steps_en' => 'Step one'])
            ->assertSessionHasNoErrors();

        $this->assertSame(["Krok jeden\nKrok dwa", 'Step one'], [Setting::find('text_packing_steps')->value, Setting::find('text_packing_steps_en')->value]);
    }
}
