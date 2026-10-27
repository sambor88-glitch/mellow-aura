<?php

namespace Tests\Feature\Gifts;

use App\Models\User;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A voucher looked up by its code in „Prezenty i zestawy” (MA-137): whether it is valid or used, and the form to give
 * it a workshop and seats or to extend it.
 */
class AdminVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 12:00', 'Europe/Warsaw'));
    }

    public function test_a_typed_code_finds_the_voucher_and_says_what_it_books(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'MA-7KQ2-9XHT', 'workshop_code' => 'couples', 'seats' => 1, 'valid_until' => '2027-03-01']);
        $owner = User::factory()->create();

        $this->get(route('admin.gifts.edit', ['kod' => $voucher->code]))->assertRedirect('/panel/logowanie');

        $this->actingAs($owner)->get(route('admin.gifts.edit', ['kod' => ' ma 7kq2 9xht ']))
            ->assertOk()
            ->assertSeeInOrder(['Sprawdź voucher', 'MA-7KQ2-9XHT', 'Ważny', 'na „Warsztat dla pary”, miejsc: 1', '<option value="couples" selected>Warsztat dla pary</option>', 'value="2027-03-01"', 'Zapisz voucher'], false);

        $this->actingAs($owner)->get(route('admin.gifts.edit', ['kod' => 'MA-ZZZZ-ZZZZ']))->assertSee('Nie ma vouchera z takim kodem.');
        $this->actingAs($owner)->get(route('admin.gifts.edit'))->assertSee('Sprawdź voucher')->assertDontSee('Nie ma vouchera z takim kodem.');
    }

    public function test_kasia_gives_a_voucher_its_workshop_and_extends_it(): void
    {
        $voucher = Voucher::factory()->create(['workshop_code' => null, 'seats' => null, 'valid_until' => '2026-10-01']);
        $owner = User::factory()->create();
        $page = route('admin.gifts.edit', ['kod' => $voucher->code]);

        $this->actingAs($owner)->get($page)->assertSee('Wygasł');

        $this->actingAs($owner)->from($page)
            ->put(route('admin.gifts.voucher.update', $voucher), ['workshop_code' => 'hand_building', 'seats' => '', 'valid_until' => '2027-01-31'])
            ->assertRedirect($page.'#voucher-'.$voucher->id)
            ->assertSessionHas('panel_status', 'Voucher '.$voucher->code.' zapisany.');

        $voucher->refresh();
        $this->assertSame(['hand_building', 1, '2027-01-31'], [$voucher->workshop_code, $voucher->seats, $voucher->valid_until->toDateString()]);

        // Back to an amount voucher: no seats.
        $this->actingAs($owner)->from($page)->put(route('admin.gifts.voucher.update', $voucher), ['workshop_code' => '', 'seats' => '2', 'valid_until' => '2027-01-31']);
        $this->assertSame([null, null], [$voucher->fresh()->workshop_code, $voucher->fresh()->seats]);
    }

    public function test_mistakes_come_back_to_the_voucher_form(): void
    {
        $voucher = Voucher::factory()->create(['workshop_code' => 'hand_building', 'seats' => 1, 'valid_until' => '2027-01-31']);
        $page = route('admin.gifts.edit', ['kod' => $voucher->code]);

        $this->actingAs(User::factory()->create())->from($page)
            ->put(route('admin.gifts.voucher.update', $voucher), ['workshop_code' => 'pottery_wheel', 'seats' => '9', 'valid_until' => ''])
            ->assertRedirect($page.'#voucher-'.$voucher->id)
            ->assertSessionHasErrorsIn('voucher-'.$voucher->id, [
                'workshop_code' => 'Wybierz warsztat z cennika',
                'seats' => 'Wpisz liczbę miejsc od 1 do 6',
                'valid_until' => 'Wpisz, do kiedy voucher jest ważny',
            ]);

        $this->assertSame(['hand_building', 1], [$voucher->fresh()->workshop_code, $voucher->fresh()->seats]);
    }
}
