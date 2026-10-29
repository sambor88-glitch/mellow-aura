<?php

namespace Tests\Feature\Firing;

use App\Models\User;
use App\Modules\Firing\Enums\FiringRequestStatus;
use App\Modules\Firing\Mail\BatchReported;
use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FiringRequestsPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        Carbon::setTestNow('2026-10-26 10:00');
    }

    public function test_open_batches_are_grouped_into_loads_by_day_with_their_totals(): void
    {
        $this->batch(['name' => 'Ola', 'quantity' => '2.5', 'quote' => 7500, 'drop_off_on' => '2026-10-29']);
        $this->batch(['name' => 'Basia', 'quantity' => '1.2', 'quote' => 3600, 'drop_off_on' => '2026-10-29', 'status' => FiringRequestStatus::Accepted]);
        $this->batch(['name' => 'Celina', 'service_code' => null, 'service_label' => null, 'unit' => null, 'quantity' => null, 'unit_price' => null, 'quote' => null, 'drop_off_on' => '2026-10-29']);
        $this->batch(['name' => 'Dorota', 'drop_off_on' => '2026-11-05', 'message' => 'Sześć miseczek <b>']);
        $this->batch(['name' => 'Ewa', 'drop_off_on' => null]);
        $this->batch(['name' => 'Fela', 'drop_off_on' => '2026-10-22', 'status' => FiringRequestStatus::Collected]);

        $this->get('/panel/wypaly/zgloszenia')->assertRedirect('/panel/logowanie');

        $this->actingAs(User::factory()->create())
            ->get('/panel/wypaly/zgloszenia')
            ->assertOk()
            ->assertSeeInOrder(['Zgłoszenia do wypału', 'Do zrobienia (5)', 'Odebrane'])
            ->assertSeeInOrder([
                'Bez terminu', 'Ewa',
                'Partia: czwartek, 29 października', '3 zgłoszenia · 3,7 kg · z cennika 111 zł · 1 do wyceny', 'Ola', 'Wypał biskwitowy · 2,5 kg · 75 zł', 'Basia', 'Przyjęte', 'Celina', 'Nie wie, jaki wypał — doradź', 'do wyceny',
                'Partia: czwartek, 5 listopada', 'Dorota',
            ])
            ->assertSee('Sześć miseczek &lt;b&gt;', false)
            ->assertDontSee('Fela');

        $this->get('/panel/wypaly/zgloszenia?widok=odebrane')
            ->assertSeeInOrder(['Do zrobienia (5)', 'Fela', 'Odebrane'])
            ->assertDontSee('Ola');
    }

    public function test_kasia_changes_a_batch_status_and_its_day(): void
    {
        $batch = $this->batch(['drop_off_on' => null]);

        $this->actingAs(User::factory()->create())
            ->patch('/panel/wypaly/zgloszenia/'.$batch->id, ['status' => 'accepted', 'drop_off_on' => '2026-10-29'])
            ->assertRedirect('/panel/wypaly/zgloszenia#zgloszenie-'.$batch->id)
            ->assertSessionHas('panel_status', 'Zapisane. Ola — przyjęte.');

        $batch->refresh();
        $this->assertSame(FiringRequestStatus::Accepted, $batch->status);
        $this->assertSame('2026-10-29', $batch->drop_off_on->toDateString());

        $this->followingRedirects()
            ->patch('/panel/wypaly/zgloszenia/'.$batch->id, ['status' => 'lost', 'drop_off_on' => 'czwartek'])
            ->assertSee('Wybierz status z listy');
        $this->assertSame(FiringRequestStatus::Accepted, $batch->fresh()->status);
    }

    public function test_a_whole_load_moves_at_once_and_picked_up_batches_stay_picked_up(): void
    {
        $first = $this->batch(['drop_off_on' => '2026-10-29']);
        $second = $this->batch(['drop_off_on' => '2026-10-29', 'status' => FiringRequestStatus::Accepted]);
        $collected = $this->batch(['drop_off_on' => '2026-10-29', 'status' => FiringRequestStatus::Collected]);
        $otherDay = $this->batch(['drop_off_on' => '2026-11-05']);

        $this->actingAs(User::factory()->create())
            ->patch('/panel/wypaly/partie/2026-10-29', ['status' => 'in_kiln'])
            ->assertRedirect('/panel/wypaly/zgloszenia#partia-2026-10-29')
            ->assertSessionHas('panel_status', 'Cała partia z 29 października — w piecu.');

        $this->assertSame(
            [FiringRequestStatus::InKiln, FiringRequestStatus::InKiln, FiringRequestStatus::Collected, FiringRequestStatus::New],
            [$first->fresh()->status, $second->fresh()->status, $collected->fresh()->status, $otherDay->fresh()->status],
        );

        $this->patch('/panel/wypaly/partie/2026-10-29', ['status' => 'everything'])->assertSessionHasErrors('status');
    }

    public function test_the_screen_is_the_owners_and_the_mail_to_kasia_links_to_it(): void
    {
        $batch = $this->batch();

        $this->actingAs(User::factory()->helper()->create())->get('/panel/wypaly/zgloszenia')->assertForbidden();

        (new BatchReported($batch))->assertSeeInHtml(route('admin.firing.requests.index').'#zgloszenie-'.$batch->id, false);

        // One section of the menu leads to the batches; the price list is the second tab (MA-249).
        $this->actingAs(User::factory()->create())->get('/panel')
            ->assertSeeInOrder(['href="'.route('admin.firing.requests.index').'"', 'Wypały'], false)
            ->assertDontSee('href="'.route('admin.firing.edit').'"', false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function batch(array $attributes = []): FiringRequest
    {
        return FiringRequest::query()->create([
            'name' => 'Ola',
            'email' => 'ola@example.com',
            'service_code' => 'bisque_firing',
            'service_label' => 'Wypał biskwitowy',
            'unit' => 'kg',
            'quantity' => '2.5',
            'unit_price' => 3000,
            'quote' => 7500,
            'drop_off_on' => '2026-10-29',
            'status' => FiringRequestStatus::New,
            ...$attributes,
        ]);
    }
}
