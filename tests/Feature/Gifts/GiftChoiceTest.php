<?php

namespace Tests\Feature\Gifts;

use App\Models\User;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Actions\AnonymizeGiftChoices;
use App\Modules\Gifts\Actions\RedeemGiftChoice;
use App\Modules\Gifts\Actions\SendDueGiftChoices;
use App\Modules\Gifts\Enums\GiftChoiceRefusal;
use App\Modules\Gifts\Enums\GiftChoiceResolution;
use App\Modules\Gifts\Exceptions\GiftChoiceNotAccepted;
use App\Modules\Gifts\Mail\GiftChoiceArrived;
use App\Modules\Gifts\Mail\GiftChoiceReminder;
use App\Modules\Gifts\Mail\GiftChoicesIssued;
use App\Modules\Gifts\Mail\VouchersIssued;
use App\Modules\Gifts\Models\GiftChoice;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\GiftChoicePdf;
use App\Modules\Gifts\Support\VoucherPdf;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * „Podaruj wybór” (MA-235): the buyer pays for a mug of a size and its delivery, the person it is for chooses the text,
 * the glaze and the address, once and by the last day.
 */
class GiftChoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        Setting::query()->updateOrCreate(['key' => 'contact_email'], ['value' => 'kasia@example.com']);
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));
    }

    public function test_the_configurator_offers_the_choice_in_polish_only(): void
    {
        $this->get('/kubek-z-napisem')
            ->assertOk()
            ->assertSeeInOrder(['Sama wpiszę napis', 'Niech wybierze obdarowana', 'id="mug-form"', 'id="gift-form"', 'name="type" value="gift_choice"'], false)
            ->assertSeeInOrder(['Dla kogo', 'Od kogo', 'Dedykacja', 'Jak przekazać kartę', 'Wydrukuję ją sama', 'Wyślij e-mailem w wybranym dniu'])
            ->assertSee('Na wybór napisu jest 60 dni')
            // 79 zł for the medium mug and 16 zł for the cheapest paid delivery.
            ->assertSeeInOrder(['79 zł za kubek + 16 zł za dostawę do obdarowanej', 'Dodaj kartę do koszyka', '95 zł'])
            ->assertSee("buyBar('#mug-form, #gift-form')", false);

        $this->get('/en/custom-text-mug')->assertOk()->assertDontSee('gift_choice')->assertDontSee('Niech wybierze obdarowana');
        $this->postJson('/en/basket', ['type' => 'gift_choice', 'size' => 'Średni', 'delivery' => 'print'])->assertNotFound();
    }

    public function test_a_card_goes_into_the_cart_at_the_mug_price_with_delivery_and_its_details_are_checked(): void
    {
        $content = $this->postJson('/koszyk', $this->card(['recipient_name' => '  Ola  ', 'sender_name' => 'Anna', 'dedication' => "Sto lat!\r\nAnia"]))
            ->assertOk()
            ->assertJson(['count' => 1, 'notice' => 'Karta wyboru w koszyku'])
            ->json('content');
        $this->assertStringContainsString('Kubek z napisem — wybiera obdarowana', $content);
        $this->assertStringContainsString('Średni 300 ml · dla: Ola · od: Anna · karta do druku', $content);
        $this->assertStringContainsString('95 zł', $content);

        // A card by e-mail goes to one address once; a second one for the same person and day stays one piece.
        $this->postJson('/koszyk', $this->card(['delivery' => 'email', 'recipient_email' => 'Ola@Example.com', 'send_on' => '2026-12-24']))
            ->assertJson(['count' => 2]);
        $this->postJson('/koszyk', $this->card(['delivery' => 'email', 'recipient_email' => 'ola@example.com', 'send_on' => '2026-12-24']))
            ->assertJson(['count' => 2]);

        $this->postJson('/koszyk', $this->card(['delivery' => 'email']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_email' => 'Wpisz e-mail osoby, która dostanie kartę — bez niego karty nie wyślę', 'send_on' => 'Wybierz dzień, w którym mam wysłać kartę']);
        $this->postJson('/koszyk', $this->card(['delivery' => 'email', 'recipient_email' => 'ola', 'send_on' => '2026-11-19']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła', 'send_on' => 'Wybierz dzień od dziś do roku naprzód']);
        $this->postJson('/koszyk', $this->card(['dedication' => str_repeat('a', 181)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dedication' => 'Dedykację zmieszczę do 180 znaków']);
        $this->postJson('/koszyk', $this->card(['size' => 'Ogromny']))->assertUnprocessable()->assertJsonValidationErrors(['size' => 'Wybierz rozmiar kubka']);
    }

    public function test_paying_gives_each_card_a_code_and_a_last_day_and_mails_it(): void
    {
        Mail::fake();
        $this->postJson('/koszyk', $this->card(['quantity' => 2, 'recipient_name' => 'Ola']));
        $this->postJson('/koszyk', $this->card(['delivery' => 'email', 'recipient_email' => 'ola@example.com', 'send_on' => '2026-11-20']));
        $this->postJson('/koszyk', $this->card(['delivery' => 'email', 'recipient_email' => 'ewa@example.com', 'send_on' => '2026-12-24', 'size' => 'Duży']));

        $this->post('/zamowienie', $this->checkout(9500 * 3 + 11100))->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::sole();
        $choices = GiftChoice::query()->orderBy('id')->get();
        $this->assertCount(4, $choices);
        $this->assertCount(4, $choices->pluck('code')->filter()->unique());
        $this->assertSame([9500, 9500, 9500, 11100], $choices->pluck('price_gross')->all());
        // 60 days from the payment, or from the day the card goes out.
        $this->assertSame(['2027-01-19', '2027-01-19', '2027-01-19', '2027-02-22'], $choices->map(fn (GiftChoice $choice) => $choice->choose_by->toDateString())->all());
        // Nothing in a parcel for the buyer: the cards are delivered by the mails.
        $this->assertSame(OrderStatus::Completed, $order->status);

        Mail::assertQueued(GiftChoicesIssued::class, fn (GiftChoicesIssued $mail) => $mail->hasTo('anna@example.com') && $mail->choices->count() === 4);
        // Today's card goes at once; Christmas Eve's waits.
        Mail::assertQueued(GiftChoiceArrived::class, 1);
        Mail::assertQueued(GiftChoiceArrived::class, fn (GiftChoiceArrived $mail) => $mail->hasTo('ola@example.com'));
        $this->assertNotNull($choices[2]->fresh()->sent_at);
        $this->assertNull($choices[3]->fresh()->sent_at);

        app(MarkOrderPaid::class)($order, 'test-repeated-confirmation');
        $this->assertSame($choices->pluck('code')->all(), GiftChoice::query()->orderBy('id')->pluck('code')->all());

        $mail = new GiftChoicesIssued($order, $choices);
        $mail->assertHasSubject('Karty wyboru z MellowAury');
        $mail->assertSeeInOrderInHtml(['Twoje karty wyboru', 'Średni 300 ml', 'dla: Ola', 'wybór do 19 stycznia 2027', $choices[0]->code, 'Wyślę ją na ewa@example.com 24 grudnia rano.']);
        $mail->assertDontSeeInHtml('95 zł');
        // The mail may be passed on as it is, so it says where the code goes (MA-288).
        $mail->assertSeeInHtml('wpisze kod na stronie <a href="'.route('gift-choice.index').'"', false);
        $mail->assertSeeInText('wpisze kod na stronie '.route('gift-choice.index').',');
        $this->assertCount(4, $mail->attachments());

        $arrived = new GiftChoiceArrived($choices[2]->fresh());
        $arrived->assertHasSubject('Masz w prezencie kubek z napisem — Ty wybierasz');
        $arrived->assertSeeInHtml($choices[2]->chooseUrl(), false);
        $arrived->assertSeeInText('Kod karty: '.$choices[2]->code);

        $pdf = app(GiftChoicePdf::class)->html($choices[0]);
        $this->assertStringContainsString($choices[0]->code, $pdf);
        $this->assertStringContainsString('Wybierz do', $pdf);
        $this->assertStringContainsString('19 stycznia 2027', $pdf);
        $this->assertStringContainsString('www.mellow-aura.com/wybierz-kubek', $pdf);
        $this->assertStringNotContainsString('95', $pdf);
    }

    public function test_an_unpaid_card_has_no_code_and_cannot_be_used(): void
    {
        $this->postJson('/koszyk', $this->card());
        $this->post('/zamowienie', $this->checkout(9500, ['payment_method' => 'bank_transfer']));

        $choice = GiftChoice::sole();
        $this->assertNull($choice->code);
        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);
    }

    public function test_she_types_the_code_and_chooses_the_text_the_glaze_and_the_address_once(): void
    {
        Mail::fake();
        $choice = $this->paidCard(['recipient_name' => 'Ola', 'sender_name' => 'Anna', 'dedication' => 'Sto lat!']);

        $this->get('/wybierz-kubek')->assertOk()->assertSee('Kod karty')->assertSee('<meta name="robots" content="noindex', false);
        $this->post('/wybierz-kubek', ['code' => 'MA-ZZZZ-ZZZZ'])
            ->assertSessionHasErrors(['code' => 'Nie znam tego kodu. Sprawdź, czy nie pomyliła się litera — kod ma 8 znaków po „MA-”.']);
        // Typed in lower case, without the dashes.
        $this->post('/wybierz-kubek', ['code' => strtolower(str_replace(['MA-', '-'], '', $choice->code))])
            ->assertRedirect($choice->chooseUrl());

        $this->get('/wybierz-kubek/'.$choice->code)->assertForbidden();
        $this->get($choice->chooseUrl())
            ->assertOk()
            ->assertSeeInOrder(['karta wyboru', 'Średni 300 ml', 'Ola, wymyśl swój napis', 'Sto lat!', 'od: Anna', 'Wybór do 19 stycznia 2027'])
            ->assertSeeInOrder(['Twój napis', 'name="text"', 'Kolor wnętrza', 'Dokąd wysłać kubek', 'InPost Paczkomat', 'opłacona', 'Zamawiam ten kubek'], false);

        $redeem = URL::signedRoute('gift-choice.redeem', ['choice' => $choice->code]);

        // The text is checked on the server, whatever the page let through.
        $this->post($redeem, $this->choice(['text' => 'TO JEST ZA DŁUGI NAPIS NA KUBEK']))->assertSessionHasErrors(['text' => 'W jednej linii zmieszczę najwyżej 16 znaków — podziel napis na linie']);
        $this->post($redeem, $this->choice(['text' => 'KAWA ☕']))->assertSessionHasErrors(['text' => 'Wbiję litery, cyfry i znaki interpunkcyjne — bez emotek']);
        $this->post($redeem, $this->choice(['locker_code' => '']))->assertSessionHasErrors('locker_code');
        $this->assertSame(1, Order::count());

        DB::enableQueryLog();
        $this->post($redeem, $this->choice(['text' => "<b>kawa</b>\r\nnajpierw"]))->assertRedirect($choice->chooseUrl());
        $locks = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query) => str_contains($query, 'gift_choices') && str_contains($query, 'for update'));
        $this->assertNotEmpty($locks, 'The card is locked while the choice is placed.');

        $order = Order::query()->latest('id')->first();
        $item = $order->items()->sole();
        $this->assertSame('Kubek z napisem', $item->product_name);
        $this->assertSame('Średni 300 ml', $item->variant_label);
        $this->assertSame("<B>KAWA</B>\nNAJPIERW", $item->custom_text);
        $this->assertSame('Kobalt', $item->custom_glaze);
        $this->assertSame(0, $item->unit_price_gross);
        $this->assertSame(0, $order->total_gross);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertSame(PaymentMethod::Voucher, $order->payment_method);
        $this->assertSame('parcel_locker', $order->shipping_method);
        $this->assertSame('KRA01M', $order->locker_code);
        $this->assertSame('ola@example.com', $order->email);
        $this->assertStringContainsString($choice->code, $order->note);

        $choice->refresh();
        $this->assertNotNull($choice->chosen_at);
        $this->assertSame($order->id, $choice->fulfilment_order_id);

        // What she typed shows escaped.
        $this->get($choice->chooseUrl())
            ->assertOk()
            ->assertSee('Kubek wybrany')
            ->assertSee('&lt;B&gt;KAWA&lt;/B&gt;', false)
            ->assertDontSee('<B>KAWA</B>', false);

        // A second choice on the same card places nothing.
        $this->post($redeem, $this->choice())->assertSessionHas('gift_choice_notice', 'Kubek z tej karty jest już wybrany i zamówiony.');
        $this->assertSame(2, Order::count());
        $this->post('/wybierz-kubek', ['code' => $choice->code])->assertSessionHasErrors(['code' => 'Kubek z tej karty jest już wybrany i zamówiony.']);

        $this->expectException(GiftChoiceNotAccepted::class);
        app(RedeemGiftChoice::class)($choice->code, 'KAWA', ['code' => 'cobalt', 'name' => 'Kobalt', 'hex' => '#24417E'], $this->choice());
    }

    public function test_a_card_past_its_last_day_chooses_nothing(): void
    {
        $choice = $this->paidCard();

        $this->travelTo(now()->setDate(2027, 1, 19)->setTime(23, 0));
        $this->get($choice->chooseUrl())->assertOk()->assertSee('Wymyśl swój napis');

        $this->travelTo(now()->setDate(2027, 1, 20)->setTime(8, 0));
        $this->get($choice->chooseUrl())
            ->assertOk()
            ->assertSeeInOrder(['Tej karty nie da się już użyć', 'Termin na wybór z tej karty minął.', 'kasia@example.com']);
        $this->post(URL::signedRoute('gift-choice.redeem', ['choice' => $choice->code]), $this->choice())
            ->assertSessionHas('gift_choice_notice', 'Termin na wybór z tej karty minął.');
        $this->assertSame(1, Order::count());

        try {
            app(RedeemGiftChoice::class)->find($choice->code);
            $this->fail('A late card was accepted.');
        } catch (GiftChoiceNotAccepted $refused) {
            $this->assertSame(GiftChoiceRefusal::Expired, $refused->refusal);
        }
    }

    public function test_cards_go_out_on_their_day_and_remind_14_and_3_days_before_the_end(): void
    {
        Mail::fake();
        $byEmail = $this->paidCard(['delivery' => 'email', 'recipient_email' => 'ola@example.com', 'send_on' => '2026-12-24']);
        $printed = $this->paidCard(['recipient_name' => 'Ewa']);
        Mail::fake();

        // Not before 8 in the morning, not before the day.
        $this->travelTo(now()->setDate(2026, 12, 23)->setTime(12, 0));
        $this->assertSame(['sent' => 0, 'reminded' => 0], app(SendDueGiftChoices::class)());
        $this->travelTo(now()->setDate(2026, 12, 24)->setTime(7, 59));
        $this->assertSame(['sent' => 0, 'reminded' => 0], app(SendDueGiftChoices::class)());
        $this->travelTo(now()->setDate(2026, 12, 24)->setTime(8, 0));
        $this->artisan('gifts:send-gift-choices')->expectsOutput('Cards sent: 1, reminders: 0')->assertSuccessful();
        Mail::assertQueued(GiftChoiceArrived::class, fn (GiftChoiceArrived $mail) => $mail->hasTo('ola@example.com'));
        $this->assertSame(['sent' => 0, 'reminded' => 0], app(SendDueGiftChoices::class)());

        // The printed card: 60 days from 20 November, so its reminders come on 5 and 16 January, to the buyer.
        $this->travelTo(now()->setDate(2027, 1, 5)->setTime(9, 0));
        $this->assertSame(['sent' => 0, 'reminded' => 1], app(SendDueGiftChoices::class)());
        Mail::assertQueued(GiftChoiceReminder::class, fn (GiftChoiceReminder $mail) => $mail->hasTo('anna@example.com') && $mail->toBuyer);
        $this->assertSame(['sent' => 0, 'reminded' => 0], app(SendDueGiftChoices::class)());
        $this->travelTo(now()->setDate(2027, 1, 16)->setTime(9, 0));
        $this->assertSame(['sent' => 0, 'reminded' => 1], app(SendDueGiftChoices::class)());
        $this->assertSame(2, $printed->fresh()->reminders_sent);

        // The card by e-mail counts from 24 December: reminders to her on 8 and 19 February, none after a choice.
        $this->travelTo(now()->setDate(2027, 2, 8)->setTime(9, 0));
        $this->assertSame(['sent' => 0, 'reminded' => 1], app(SendDueGiftChoices::class)());
        Mail::assertQueued(GiftChoiceReminder::class, fn (GiftChoiceReminder $mail) => $mail->hasTo('ola@example.com') && ! $mail->toBuyer);
        $byEmail->update(['chosen_at' => now()]);
        $this->travelTo(now()->setDate(2027, 2, 19)->setTime(9, 0));
        $this->assertSame(['sent' => 0, 'reminded' => 0], app(SendDueGiftChoices::class)());

        $reminder = new GiftChoiceReminder($printed->fresh(), toBuyer: true);
        $reminder->assertSeeInHtml('Przypomnij osobie, która ją dostała');
        $reminder->assertDontSeeInHtml('Wybieram napis i kolor');
    }

    public function test_kasia_sees_the_cards_sends_one_again_and_decides_after_the_deadline(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $choice = $this->paidCard(['recipient_name' => 'Ola']);

        $this->actingAs($owner)->get(route('admin.gift-choices.index'))
            ->assertOk()
            ->assertSeeInOrder(['Karty wyboru', $choice->code, 'Średni 300 ml · dla: Ola', 'Kupiła Anna Nowak', 'karta do druku', 'Wybór do 19.01.2027', 'Czeka na wybór', 'Wyślij PDF kupującej'])
            ->assertSeeInOrder(['Zasady karty', 'Czas na wybór napisu', 'value="60"', 'Dostawa doliczona do ceny karty', 'teraz 16 zł'], false);
        $this->actingAs($owner)->get(route('admin.orders.show', $choice->orderItem->order))->assertOk()->assertSeeInOrder(['Karty wyboru', $choice->code, 'Czeka na wybór']);

        Mail::fake();
        $this->actingAs($owner)->post(route('admin.gift-choices.resend', $choice))->assertSessionHas('panel_status', 'Karta '.$choice->code.' w PDF wysłana jeszcze raz do kupującej.');
        Mail::assertQueued(GiftChoicesIssued::class, fn (GiftChoicesIssued $mail) => $mail->hasTo('anna@example.com'));

        // Before the deadline there is nothing to decide.
        $this->actingAs($owner)->post(route('admin.gift-choices.resolve', $choice), ['resolution' => 'voucher'])
            ->assertSessionHas('panel_status', 'Karta '.$choice->code.' jest już załatwiona albo wciąż czeka na wybór.');
        $this->assertSame(0, Voucher::count());

        $this->travelTo(now()->setDate(2027, 1, 21)->setTime(9, 0));
        $this->actingAs($owner)->get(route('admin.dashboard'))->assertSeeInOrder(['Karty wyboru', '1']);
        $this->actingAs($owner)->get(route('admin.gift-choices.index'))
            ->assertSeeInOrder(['Po terminie — Twoja decyzja', $choice->code, 'Po terminie', 'Kubek bez napisu', 'Zamień na voucher']);

        $this->actingAs($owner)->post(route('admin.gift-choices.resolve', $choice), ['resolution' => 'voucher'])
            ->assertSessionHas('panel_status', 'Karta '.$choice->code.' zamieniona na voucher — kupująca dostała go mailem.');
        $voucher = Voucher::sole();
        $this->assertSame(9500, $voucher->amount_minor);
        $this->assertSame(9500, $voucher->balance_minor);
        $this->assertSame('Ola', $voucher->recipient_name);
        $this->assertSame('2028-01-21', $voucher->valid_until->toDateString());
        $this->assertSame(GiftChoiceResolution::Voucher, $choice->fresh()->resolution);
        Mail::assertQueued(VouchersIssued::class, fn (VouchersIssued $mail) => $mail->hasTo('anna@example.com') && $mail->vouchers->first()->is($voucher));
        // It has no product in the shop, so the PDF says what it is worth.
        $this->assertStringContainsString('voucher · 95 zł', app(VoucherPdf::class)->html($voucher));

        // Decided once: a second click makes no second voucher.
        $this->actingAs($owner)->post(route('admin.gift-choices.resolve', $choice), ['resolution' => 'plain_mug']);
        $this->assertSame(1, Voucher::count());
        $this->assertSame(GiftChoiceResolution::Voucher, $choice->fresh()->resolution);

        $this->actingAs($owner)->put(route('admin.gift-choices.settings'), ['gift_choice_days' => 90, 'gift_choice_delivery' => '19,90'])
            ->assertSessionHas('panel_status', 'Zapisane. Nowe karty już tak działają.');
        $this->assertSame(1990, Setting::query()->where('key', 'gift_choice_delivery_gross')->value('value'));
        $this->actingAs($owner)->put(route('admin.gift-choices.settings'), ['gift_choice_days' => 3])
            ->assertSessionHasErrorsIn('ustawienia', ['gift_choice_days' => 'Wpisz liczbę dni od 7 do 365']);
    }

    public function test_forgetting_a_customer_wipes_her_names_and_her_address_but_keeps_the_card_usable(): void
    {
        Mail::fake();
        $bought = $this->paidCard(['recipient_name' => 'Ola', 'sender_name' => 'Anna', 'dedication' => 'Sto lat!']);
        $received = $this->paidCard(['delivery' => 'email', 'recipient_email' => 'ewa@example.com', 'send_on' => '2026-11-20']);
        $waiting = $this->paidCard(['delivery' => 'email', 'recipient_email' => 'ewa@example.com', 'send_on' => '2026-12-24']);

        $this->assertSame(2, app(AnonymizeGiftChoices::class)([$bought->order_item_id], 'ewa@example.com'));

        $bought->refresh();
        $this->assertNull($bought->recipient_name);
        $this->assertNull($bought->sender_name);
        $this->assertNull($bought->dedication);
        $this->assertNotNull($bought->code);
        $this->assertNull($received->fresh()->recipient_email);
        // Not sent yet: the address is where it still has to go.
        $this->assertSame('ewa@example.com', $waiting->fresh()->recipient_email);
        $this->get($bought->chooseUrl())->assertOk()->assertSee('Wymyśl swój napis');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function card(array $overrides = []): array
    {
        return ['type' => 'gift_choice', 'size' => 'Średni', 'delivery' => 'print', ...$overrides];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function checkout(int $total, array $overrides = []): array
    {
        return [
            'phone' => '+48 600 100 200',
            'email' => 'anna@example.com',
            'name' => 'Anna Nowak',
            'payment_method' => 'blik',
            'blik_code' => '123456', 'accept_terms' => '1',
            'expected_total' => $total,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function choice(array $overrides = []): array
    {
        return [
            'text' => 'KAWA NAJPIERW',
            'glaze' => 'cobalt',
            'name' => 'Ola Kowalska',
            'email' => 'ola@example.com',
            'phone' => '600 200 300',
            'shipping_method' => 'parcel_locker',
            'locker_code' => 'kra01m',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function paidCard(array $card = []): GiftChoice
    {
        $this->postJson('/koszyk', $this->card($card))->assertOk();
        $this->post('/zamowienie', $this->checkout(9500))->assertRedirect('/zamowienie/potwierdzenie');
        // The confirmation empties the cart for the next card.
        $this->get('/zamowienie/potwierdzenie')->assertOk();

        return GiftChoice::query()->latest('id')->firstOrFail();
    }
}
