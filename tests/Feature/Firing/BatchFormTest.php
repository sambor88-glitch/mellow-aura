<?php

namespace Tests\Feature\Firing;

use App\Models\User;
use App\Modules\Firing\Mail\BatchReportConfirmed;
use App\Modules\Firing\Mail\BatchReported;
use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class BatchFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        // A Monday.
        Carbon::setTestNow('2026-10-26 10:00');
    }

    public function test_the_form_lists_the_price_list_and_the_next_load_days_from_the_panel(): void
    {
        $this->get('/wypal-ceramiki-krakow')
            ->assertSeeInOrder(['Zgłoś wsad', 'Rodzaj wypału', 'Wypał biskwitowy', 'Cała półka na wyłączność', 'Nie wiem, doradź', 'Wyślij zgłoszenie'])
            ->assertSee('\u0022code\u0022:\u0022bisque_firing\u0022,\u0022label\u0022:\u0022Wypał biskwitowy\u0022,\u0022price\u0022:3000,\u0022unit\u0022:\u0022kg\u0022', false)
            ->assertDontSee('Kiedy przywieziesz prace?');

        $this->setting('kiln_load_weekday', 4);

        $this->get('/wypal-ceramiki-krakow')
            ->assertSeeInOrder(['Kiedy przywieziesz prace?', 'value="2026-10-29"', 'czwartek, 29 października', 'value="2026-11-05"', 'value="2026-11-12"', 'value="2026-11-19"'], false)
            ->assertDontSee('value="2026-11-26"', false);
    }

    public function test_a_batch_by_weight_is_saved_with_the_quote_and_mailed_to_kasia_and_the_customer(): void
    {
        Mail::fake();
        $this->setting('contact_email', 'kasia@example.com');
        $this->setting('kiln_load_weekday', 4);

        $this->post('/wypal-ceramiki-krakow', $this->form(['quantity' => '2,5', 'drop_off_on' => '2026-11-05']))
            ->assertRedirect(route('firing.index').'#zglos-wsad');

        $batch = FiringRequest::sole();
        $this->assertSame(['bisque_firing', 'Wypał biskwitowy', 'kg', '2.50', 3000, 7500, '2026-11-05', 'new'],
            [$batch->service_code, $batch->service_label, $batch->unit, $batch->quantity, $batch->unit_price, $batch->quote, $batch->drop_off_on->toDateString(), $batch->status->value]);

        Mail::assertQueued(BatchReported::class, fn (BatchReported $mail) => $mail->hasTo('kasia@example.com')
            && $mail->hasReplyTo('ola@example.com', 'Ola')
            && $mail->hasSubject('Zgłoszenie wsadu: Wypał biskwitowy — Ola'));
        Mail::assertQueued(BatchReportConfirmed::class, fn (BatchReportConfirmed $mail) => $mail->hasTo('ola@example.com')
            && $mail->hasReplyTo('kasia@example.com'));

        $this->get('/wypal-ceramiki-krakow')
            ->assertSee('Mam Twoje zgłoszenie — wycena 75,00 zł. Potwierdzenie poszło na ola@example.com.');
        $this->get('/wypal-ceramiki-krakow')->assertDontSee('Mam Twoje zgłoszenie');
    }

    public function test_the_quote_counts_pieces_a_flat_price_and_none_for_advice(): void
    {
        Mail::fake();
        $this->setting('contact_email', 'kasia@example.com');

        $this->post('/wypal-ceramiki-krakow', $this->form(['service' => 'gold_firing', 'quantity' => '3']));
        $this->post('/wypal-ceramiki-krakow', $this->form(['service' => 'private_shelf', 'quantity' => '99']));
        $this->post('/wypal-ceramiki-krakow', $this->form(['service' => 'advice', 'quantity' => '2']));

        $this->assertSame([
            ['gold_firing', '3.00', 10500],
            ['private_shelf', null, 18000],
            [null, null, null],
        ], FiringRequest::query()->orderBy('id')->get()->map(fn (FiringRequest $batch) => [$batch->service_code, $batch->quantity, $batch->quote])->all());
    }

    public function test_a_row_saved_without_a_unit_counts_by_its_za_note(): void
    {
        Mail::fake();
        $this->setting('kiln_prices', [
            ['code' => 'glaze_firing', 'label' => 'Wypał na ostro', 'price_gross' => 4000, 'unit' => null, 'unit_label' => '/ kg'],
            // Kasia changed „/ kg” to „/ szt.” and the old unit stayed behind.
            ['code' => 'gold_firing', 'label' => 'Wypał złota', 'price_gross' => 3500, 'unit' => 'kg', 'unit_label' => '/ szt.'],
        ]);

        $this->post('/wypal-ceramiki-krakow', $this->form(['service' => 'glaze_firing', 'quantity' => '3']));
        $this->post('/wypal-ceramiki-krakow', $this->form(['service' => 'gold_firing', 'quantity' => '2']));

        $this->assertSame([['kg', 12000], ['piece', 7000]],
            FiringRequest::query()->orderBy('id')->get()->map(fn (FiringRequest $batch) => [$batch->unit, $batch->quote])->all());
    }

    public function test_both_mails_show_the_batch_and_escape_what_the_customer_wrote(): void
    {
        $batch = FiringRequest::query()->create([
            'name' => 'Ola <b>', 'email' => 'ola@example.com', 'phone' => '600 100 200',
            'service_code' => 'bisque_firing', 'service_label' => 'Wypał biskwitowy', 'unit' => 'kg', 'quantity' => '2.5',
            'unit_price' => 3000, 'quote' => 7500, 'drop_off_on' => '2026-10-29', 'message' => "Sześć miseczek\n<script>alert(1)</script>",
        ]);

        $toKasia = new BatchReported($batch);
        $toKasia->assertSeeInOrderInHtml(['Wypał biskwitowy', '2,5 kg', '30,00 zł / kg', 'czwartek, 29 października 2026', '75,00 zł', '600 100 200'], false);
        $toKasia->assertSeeInHtml('Ola &lt;b&gt;', false);
        $toKasia->assertDontSeeInHtml('<script>alert(1)</script>', false);
        $toKasia->assertSeeInText("Wycena: 75,00 zł\n\nSześć miseczek\n<script>alert(1)</script>", false);

        $toCustomer = new BatchReportConfirmed($batch);
        $toCustomer->assertSeeInOrderInHtml(['Mam Twoje zgłoszenie', 'Wypał biskwitowy', '2,5 kg', '75,00 zł', 'Kasia'], false);
        $toCustomer->assertDontSeeInHtml('<script>alert(1)</script>', false);

        $batch->update(['service_code' => null, 'service_label' => null, 'unit' => null, 'quantity' => null, 'unit_price' => null, 'quote' => null]);
        (new BatchReportConfirmed($batch->fresh()))->assertSeeInOrderInText(['Kwotę podam, gdy zobaczę prace', 'Wypał: Nie wiem, doradź', 'Wycena: po obejrzeniu prac']);
    }

    public function test_the_form_says_what_is_missing_and_keeps_what_was_written(): void
    {
        Mail::fake();
        $this->setting('kiln_load_weekday', 4);

        $this->followingRedirects()
            ->post('/wypal-ceramiki-krakow', $this->form(['name' => '', 'email' => 'ola.example.com', 'quantity' => '', 'drop_off_on' => '2026-10-22']))
            ->assertSee('Wpisz imię — żebym wiedziała, jak się do Ciebie zwracać')
            ->assertSee('Adres e-mail bez małpy — sprawdź, czy nie uciekła')
            ->assertSee('Wpisz, ile mniej więcej ważą prace, np. 2,5')
            ->assertSee('Ten dzień już minął — wybierz któryś z listy')
            ->assertSee('value="ola.example.com"', false)
            ->assertSee('Sześć miseczek');

        $this->followingRedirects()
            ->post('/wypal-ceramiki-krakow', $this->form(['service' => 'gold_firing', 'quantity' => '2,5', 'drop_off_on' => '']))
            ->assertSee('Wpisz liczbę prac, np. 4')
            ->assertSee('Wybierz dzień, w którym przywieziesz prace');

        $this->followingRedirects()
            ->post('/wypal-ceramiki-krakow', $this->form(['service' => 'raku', 'quantity' => '0']))
            ->assertSee('Wybierz rodzaj wypału z listy');

        $this->assertDatabaseCount('firing_requests', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_a_bot_that_fills_the_hidden_field_saves_and_sends_nothing(): void
    {
        Mail::fake();

        $this->post('/wypal-ceramiki-krakow', $this->form(['website' => 'https://spam.example']))
            ->assertRedirect(route('firing.index').'#zglos-wsad');

        $this->assertDatabaseCount('firing_requests', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_without_an_e_mail_in_the_panel_the_batch_is_saved_and_the_customer_still_gets_her_copy(): void
    {
        Exceptions::fake();
        Mail::fake();

        $this->post('/wypal-ceramiki-krakow', $this->form())->assertRedirect();

        $this->assertDatabaseCount('firing_requests', 1);
        Mail::assertNotQueued(BatchReported::class);
        Mail::assertQueued(BatchReportConfirmed::class);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_the_panel_saves_the_day_batches_are_collected(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/panel/wypaly')
            ->assertSeeInOrder(['Dzień, w którym zbierasz wsad', 'Bez stałego dnia — umawiam się mailem', 'czwartek']);

        $this->put('/panel/wypaly', ['kiln_load_weekday' => '4', 'prices' => []])->assertRedirect('/panel/wypaly#cennik');
        $this->assertSame(4, Setting::find('kiln_load_weekday')->value);
        $this->get('/panel/wypaly')->assertSee('<option value="4" selected>czwartek</option>', false);

        $this->put('/panel/wypaly', ['kiln_load_weekday' => '', 'prices' => []]);
        $this->assertNull(Setting::find('kiln_load_weekday')->value);

        $this->followingRedirects()->put('/panel/wypaly', ['kiln_load_weekday' => '9'])->assertSee('Wybierz dzień z listy');
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Ola',
            'email' => 'ola@example.com',
            'phone' => '',
            'service' => 'bisque_firing',
            'quantity' => '2,5',
            'message' => 'Sześć miseczek, glina szamotowa',
            'website' => '',
            ...$overrides,
        ];
    }

    private function setting(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->app->make(Settings::class)->refresh();
    }
}
