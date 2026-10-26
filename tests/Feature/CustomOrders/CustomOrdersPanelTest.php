<?php

namespace Tests\Feature\CustomOrders;

use App\Models\User;
use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Mail\BriefReceived;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomOrdersPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_open_ideas_are_listed_oldest_first_with_their_status_tabs(): void
    {
        Carbon::setTestNow('2026-10-20 10:00');
        $this->idea(['name' => 'Ola', 'quantity' => '6', 'budget' => 'ok. 600 zł', 'description' => 'Sześć kubków <b>na ślub</b>']);
        Carbon::setTestNow('2026-10-21 10:00');
        $this->idea(['name' => 'Basia', 'kind' => BriefKind::Scarf, 'status' => CustomOrderStatus::Quoting, 'quote' => 45050, 'notes' => 'Zadzwonić w piątek']);
        Carbon::setTestNow('2026-10-22 10:00');
        $this->idea(['name' => 'Celina', 'status' => CustomOrderStatus::Shipped]);
        $this->idea(['name' => 'Dorota', 'status' => CustomOrderStatus::Declined]);

        $this->get('/panel/zamowienia-indywidualne')->assertRedirect('/panel/logowanie');

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia-indywidualne')
            ->assertOk()
            ->assertSeeInOrder(['Zamówienia indywidualne', 'Do zrobienia (2)', 'Nowe (1)', 'Wycena (1)', 'Zaliczka (0)', 'W realizacji (0)', 'Wysłane (1)', 'Odrzucone (1)'])
            ->assertSeeInOrder([
                'Ola', 'Kubki z napisem · ile: 6 · budżet: ok. 600 zł',
                'Basia', 'Opaska z Twojej apaszki · wycena 450,50 zł', 'Wycena', 'value="450,50"', 'Zadzwonić w piątek',
            ], false)
            ->assertSee('Sześć kubków &lt;b&gt;na ślub&lt;/b&gt;', false)
            ->assertDontSee('Celina')
            ->assertDontSee('Dorota');

        $this->get('/panel/zamowienia-indywidualne?status=quoting')
            ->assertSee('Basia')
            ->assertDontSee('Ola');

        $this->get('/panel/zamowienia-indywidualne?status=shipped')
            ->assertSee('Celina')
            ->assertDontSee('Basia');

        $this->get('/panel/zamowienia-indywidualne?status=declined')
            ->assertSee('Dorota')
            ->assertDontSee('Celina')
            ->assertDontSee('Basia');

        $this->get('/panel/zamowienia-indywidualne?status=deposit')
            ->assertSee('Nic w „Zaliczka”');
    }

    public function test_kasia_saves_the_status_a_quote_in_grosze_and_her_note(): void
    {
        $idea = $this->idea();

        $this->actingAs(User::factory()->create())
            ->patch('/panel/zamowienia-indywidualne/'.$idea->id, ['status' => 'quoting', 'quote' => '1250,5', 'notes' => '  Szkliwo jak na zdjęciu 2  '])
            ->assertRedirect('/panel/zamowienia-indywidualne#pomysl-'.$idea->id)
            ->assertSessionHas('panel_status', 'Zapisane. Ola — wycena.');

        $idea->refresh();
        $this->assertSame(CustomOrderStatus::Quoting, $idea->status);
        $this->assertSame(125050, $idea->quote);
        $this->assertSame('Szkliwo jak na zdjęciu 2', $idea->notes);

        $this->patch('/panel/zamowienia-indywidualne/'.$idea->id, ['status' => 'deposit', 'quote' => '', 'notes' => ''])->assertRedirect();
        $idea->refresh();
        $this->assertNull($idea->quote);
        $this->assertNull($idea->notes);

        $this->followingRedirects()
            ->patch('/panel/zamowienia-indywidualne/'.$idea->id, ['status' => 'lost', 'quote' => 'dużo'])
            ->assertSee('Wybierz status z listy');
        $this->patch('/panel/zamowienia-indywidualne/'.$idea->id, ['status' => 'quoting', 'quote' => '12.345'])
            ->assertSessionHasErrors('quote', null, 'pomysl');
        $this->assertSame(CustomOrderStatus::Deposit, $idea->fresh()->status);

        $this->patch('/panel/zamowienia-indywidualne/'.$idea->id, ['status' => 'declined', 'quote' => '', 'notes' => 'Za mało czasu przed ślubem'])
            ->assertSessionHas('panel_status', 'Zapisane. Ola — odrzucone.');
        $this->assertSame(CustomOrderStatus::Declined, $idea->fresh()->status);
    }

    public function test_photos_from_the_private_disk_show_only_to_the_owner_and_only_under_their_idea(): void
    {
        $idea = $this->idea();
        $photo = $idea->addMedia(UploadedFile::fake()->image('kubek.webp', 40, 40))
            ->usingFileName('pomysl-'.$idea->id.'-1.webp')
            ->toMediaCollection('photos');
        $other = $this->idea(['name' => 'Basia']);

        $url = '/panel/zamowienia-indywidualne/'.$idea->id.'/zdjecia/'.$photo->id;

        $this->get($url)->assertRedirect('/panel/logowanie');
        $this->actingAs(User::factory()->helper()->create())->get($url)->assertForbidden();

        $owner = User::factory()->create();
        $this->actingAs($owner)
            ->get('/panel/zamowienia-indywidualne')
            ->assertSee('src="'.url($url).'"', false);

        $this->actingAs($owner)->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->actingAs($owner)->get('/panel/zamowienia-indywidualne/'.$other->id.'/zdjecia/'.$photo->id)->assertNotFound();
    }

    public function test_the_screen_is_the_owners_and_the_mail_to_kasia_links_to_it(): void
    {
        $idea = $this->idea();

        $this->actingAs(User::factory()->helper()->create())->get('/panel/zamowienia-indywidualne')->assertForbidden();
        $this->actingAs(User::factory()->helper()->create())->patch('/panel/zamowienia-indywidualne/'.$idea->id, ['status' => 'shipped'])->assertForbidden();

        (new BriefReceived($idea))->assertSeeInHtml(route('admin.custom-orders.index').'#pomysl-'.$idea->id, false);
        (new BriefReceived($idea))->assertSeeInText(route('admin.custom-orders.index').'#pomysl-'.$idea->id);

        $this->actingAs(User::factory()->create())->get('/panel')->assertSee('Zamówienia indywidualne');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function idea(array $attributes = []): CustomOrderRequest
    {
        return CustomOrderRequest::query()->create([
            'name' => 'Ola',
            'email' => 'ola@example.com',
            'kind' => BriefKind::Mugs,
            'description' => 'Kubki dla świadków',
            'status' => CustomOrderStatus::New,
            ...$attributes,
        ]);
    }
}
