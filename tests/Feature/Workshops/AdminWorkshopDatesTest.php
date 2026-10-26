<?php

namespace Tests\Feature\Workshops;

use App\Models\User;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkshopDatesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->owner = User::factory()->create();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $date = WorkshopDate::factory()->create();

        $this->post('/panel/warsztaty/terminy')->assertRedirect('/panel/logowanie');
        $this->delete('/panel/warsztaty/terminy/'.$date->id)->assertRedirect('/panel/logowanie');
        $this->assertModelExists($date);
    }

    public function test_a_date_is_added_from_the_calendar_and_kept_in_warsaw_time(): void
    {
        $this->actingAs($this->owner)
            ->post('/panel/warsztaty/terminy', ['workshop' => 'hand_building', 'date' => '2026-11-14', 'start' => '11:00', 'end' => '13:00', 'seats' => '6'])
            ->assertRedirect('/panel/warsztaty#terminy')
            ->assertSessionHas('panel_status', 'Termin dodany do terminarza');

        $date = WorkshopDate::query()->sole();
        $this->assertSame(['hand_building', 6], [$date->workshop_code, $date->seats]);
        // ISO 8601 with the offset, as Event and CourseInstance need it; 14 November is winter time.
        $this->assertSame('2026-11-14T11:00:00+01:00', $date->starts_at->toIso8601String());
        $this->assertSame('2026-11-14T13:00:00+01:00', $date->ends_at->toIso8601String());

        $this->get('/panel/warsztaty')
            ->assertOk()
            ->assertSeeInOrder(['Terminarz', 'sobota, 14 listopada', '11:00 – 13:00 · Lepienie kubków', '6 miejsc', 'Dodaj termin']);
    }

    public function test_the_end_is_optional_and_summer_time_gets_its_own_offset(): void
    {
        $this->actingAs($this->owner)
            ->post('/panel/warsztaty/terminy', ['workshop' => 'glazing', 'date' => '2027-04-10', 'start' => '18:00', 'seats' => '1'])
            ->assertRedirect('/panel/warsztaty#terminy');

        $date = WorkshopDate::query()->sole();
        $this->assertNull($date->ends_at);
        $this->assertSame('2027-04-10T18:00:00+02:00', $date->starts_at->toIso8601String());

        $this->get('/panel/warsztaty')->assertSeeInOrder(['sobota, 10 kwietnia', '18:00 · Szkliwienie i malowanie', '1 miejsce']);
    }

    public function test_the_list_shows_upcoming_dates_nearest_first_and_names_a_workshop_gone_from_the_price_list(): void
    {
        WorkshopDate::factory()->create(['starts_at' => now()->addDays(20)->setTime(11, 0), 'seats' => 22]);
        WorkshopDate::factory()->create(['workshop_code' => 'kiln_tour', 'starts_at' => now()->addDays(5)->setTime(17, 0), 'ends_at' => null, 'seats' => 12]);
        WorkshopDate::factory()->create(['starts_at' => now()->subDay(), 'seats' => 3]);

        $this->actingAs($this->owner)
            ->get('/panel/warsztaty')
            ->assertSeeInOrder(['sobota, 7 listopada', '17:00 · warsztatu nie ma już w cenniku', '12 miejsc', 'niedziela, 22 listopada', '11:00 – 13:00 · Lepienie kubków', '22 miejsca'])
            ->assertDontSee('3 miejsca')
            ->assertDontSee('<option value="kiln_tour"', false);
    }

    public function test_an_empty_calendar_says_so(): void
    {
        $this->actingAs($this->owner)->get('/panel/warsztaty')->assertSee('Nie ma jeszcze żadnego terminu');
    }

    public function test_mistakes_are_explained_on_their_field(): void
    {
        $this->actingAs($this->owner)->from('/panel/warsztaty');
        $post = fn (array $fields) => $this->post('/panel/warsztaty/terminy', [...['workshop' => 'hand_building', 'date' => '2026-11-14', 'start' => '11:00', 'seats' => '6'], ...$fields]);

        $post(['workshop' => 'kiln_tour', 'date' => '14.11.2026', 'start' => '', 'seats' => '0'])
            ->assertRedirect('/panel/warsztaty#terminy')
            ->assertSessionHasErrorsIn('terminy', [
                'workshop' => 'Tego warsztatu nie ma już w cenniku — wybierz inny',
                'date' => 'Wybierz dzień z kalendarza',
                'start' => 'Wpisz godzinę początku, np. 11:00',
                'seats' => 'Wpisz co najmniej jedno miejsce',
            ]);
        $post(['date' => '2026-11-01'])->assertSessionHasErrorsIn('terminy', ['date' => 'Ten dzień już minął — wybierz dzisiejszy albo późniejszy']);
        $post(['date' => '2026-11-02', 'start' => '09:30'])->assertSessionHasErrorsIn('terminy', ['start' => 'Ta godzina już minęła — wpisz późniejszą']);
        $post(['end' => '10:00'])->assertSessionHasErrorsIn('terminy', ['end' => 'Koniec musi być po początku']);
        $post(['seats' => '31'])->assertSessionHasErrorsIn('terminy', ['seats' => 'Zmieszczę do 30 miejsc']);

        $post([])->assertSessionDoesntHaveErrors();
        $post(['seats' => '4'])->assertSessionHasErrorsIn('terminy', ['start' => 'Ten termin już jest w terminarzu']);
        $this->assertSame(1, WorkshopDate::query()->count());

        $this->get('/panel/warsztaty')->assertDontSee('Popraw zaznaczone pola, żeby dodać termin');
    }

    public function test_the_form_keeps_what_was_typed_after_a_mistake(): void
    {
        $this->actingAs($this->owner)
            ->from('/panel/warsztaty')
            ->followingRedirects()
            ->post('/panel/warsztaty/terminy', ['workshop' => 'couples', 'date' => '2026-11-14', 'start' => '18:30', 'end' => '18:00', 'seats' => '2'])
            ->assertSee('Popraw zaznaczone pola, żeby dodać termin')
            ->assertSee('<option value="couples" selected', false)
            ->assertSee('value="18:30"', false)
            ->assertSee('id="termin-end-note"', false);
    }

    public function test_a_date_is_removed(): void
    {
        $date = WorkshopDate::factory()->create();

        $this->actingAs($this->owner)
            ->delete('/panel/warsztaty/terminy/'.$date->id)
            ->assertRedirect('/panel/warsztaty#terminy')
            ->assertSessionHas('panel_status', 'Termin usunięty z terminarza');
        $this->assertModelMissing($date);
    }
}
