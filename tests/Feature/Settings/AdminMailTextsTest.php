<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Mail\ParcelOnItsWay;
use App\Modules\Checkout\Mail\WithdrawalConfirmed;
use App\Modules\Checkout\Support\SampleOrder;
use App\Modules\Gifts\Mail\VouchersIssued;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Workshops\Mail\BookingConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminMailTextsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_panel_lists_the_customer_mails_with_the_defaults_in_empty_fields(): void
    {
        Setting::create(['key' => 'mail_order_confirmed_intro', 'value' => 'Mam Twoją wpłatę za {numer}.']);

        $this->actingAs(User::factory()->create())
            ->get('/panel/maile')
            ->assertOk()
            ->assertSee('Potwierdzenie zamówienia')
            ->assertSee('Potwierdzenie odstąpienia od umowy')
            ->assertSee('Mam Twoją wpłatę za {numer}.')
            ->assertSee('placeholder="Zamówienie {numer} jest opłacone"', false)
            ->assertSee('placeholder="Order {numer} is paid"', false)
            ->assertSee('placeholder="Kasia"', false)
            ->assertSee('name="mail_parcel_on_its_way_outro_en"', false)
            // A mail without an English template has no English fields.
            ->assertDontSee('name="mail_withdrawal_confirmed_subject_en"', false)
            ->assertDontSee('Nowe zamówienie');
    }

    public function test_a_helper_account_does_not_get_the_screen(): void
    {
        $this->actingAs(User::factory()->helper()->create())->get('/panel/maile')->assertForbidden();
    }

    public function test_saved_texts_go_out_escaped_with_the_order_filled_in_and_empty_ones_fall_back(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/panel/maile', [
                'mail_order_confirmed_subject' => ' Płatność za {numer} doszła ',
                'mail_order_confirmed_intro' => "Mam <b>wpłatę</b>\r\nza {numer}.",
                'mail_order_confirmed_outro' => '',
                'mail_signature' => "Kasia\nMellowAura",
                'mail_footer' => 'Pracownia ceramiki',
                'mail_order_confirmed_intro_en' => 'Got your payment for {numer}.',
            ])
            ->assertRedirect('/panel/maile#maile');

        $this->assertSame('Płatność za {numer} doszła', Setting::find('mail_order_confirmed_subject')->value);
        $this->assertNull(Setting::find('mail_order_confirmed_outro')->value);

        Setting::create(['key' => 'contact_email', 'value' => 'kontakt@example.com']);
        $order = SampleOrder::make('pl');
        $mail = new OrderConfirmed($order);

        $html = $mail->render();
        $this->assertStringContainsString('Mam &lt;b&gt;wpłatę&lt;/b&gt;<br>'."\n".'za <strong style="font-weight: 600;">'.$order->number.'</strong>.', $html);
        $this->assertStringContainsString('Masz pytanie o zamówienie? Odpisz na tego maila', $html);
        $this->assertStringContainsString('Kasia<br>'."\n".'MellowAura', $html);
        $this->assertStringContainsString('Pracownia ceramiki<br>', $html);
        $this->assertSame('Płatność za '.$order->number.' doszła', $mail->envelope()->subject);

        $plain = view($mail->content()->text, $mail->content()->with)->render();
        $this->assertStringContainsString("Mam <b>wpłatę</b>\nza ".$order->number.'.', $plain);

        $english = new OrderConfirmed(SampleOrder::make('en'));
        $this->assertStringContainsString('Got your payment for <strong', $english->render());
        $this->assertSame('Order '.$order->number.' is paid', $english->withLocale('en', fn () => $english->envelope()->subject));
    }

    public function test_the_subject_goes_out_in_the_order_language(): void
    {
        Setting::create(['key' => 'mail_parcel_on_its_way_subject', 'value' => 'Paczka {numer} wyszła']);

        $polish = ParcelOnItsWay::sample('pl');
        $english = ParcelOnItsWay::sample('en');

        $this->assertSame('Paczka '.$polish->order->number.' wyszła', $polish->withLocale('pl', fn () => $polish->envelope()->subject));
        $this->assertSame('Order '.$english->order->number.' is on its way', $english->withLocale('en', fn () => $english->envelope()->subject));
    }

    public function test_texts_are_checked_for_length_lines_and_placeholders(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/panel/maile')
            ->put('/panel/maile', [
                'mail_order_confirmed_subject' => "Dwie\nlinijki",
                'mail_order_confirmed_intro' => 'Zamówienie {nr} jest opłacone.',
                'mail_footer' => 'Stopka z {numer}',
                'mail_parcel_on_its_way_outro' => str_repeat('a', 301),
            ])
            ->assertRedirect('/panel/maile#maile')
            ->assertSessionHasErrors(['mail_order_confirmed_subject', 'mail_order_confirmed_intro', 'mail_footer', 'mail_parcel_on_its_way_outro'], null, 'maile');

        $this->assertSame(0, Setting::query()->where('key', 'like', 'mail_%')->count());
    }

    public function test_the_preview_shows_the_typed_texts_for_a_sample_order_without_saving_them(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->put('/panel/maile/podglad', [
                'preview' => 'order_confirmed:pl',
                'mail_order_confirmed_subject' => 'Opłacone: {numer}',
                'mail_order_confirmed_intro' => 'Dziękuję za <zakupy>!',
                'mail_signature' => 'Kasia S.',
            ])
            ->assertOk()
            ->assertSee('Opłacone: MA-')
            ->assertSee('Dziękuję za &amp;lt;zakupy&amp;gt;!', false)
            ->assertSee('Kasia S.');

        $this->assertSame(0, Setting::query()->where('key', 'like', 'mail_%')->count());

        $this->actingAs(User::factory()->create())
            ->put('/panel/maile/podglad', ['preview' => 'parcel_on_its_way:en', 'mail_parcel_on_its_way_intro_en' => 'Off it goes: {numer}.'])
            ->assertOk()
            ->assertSee('Order MA-')
            ->assertSee('Off it goes:');

        $this->actingAs(User::factory()->create())
            ->put('/panel/maile/podglad', ['preview' => 'withdrawal_confirmed:pl'])
            ->assertOk()
            ->assertSee('Potwierdzenie odstąpienia od umowy');

        $this->actingAs(User::factory()->create())
            ->put('/panel/maile/podglad', ['preview' => 'new_order:pl'])
            ->assertNotFound();
    }

    public function test_the_workshop_custom_order_voucher_and_firing_mails_are_on_the_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/panel/maile')
            ->assertOk()
            ->assertSee('Potwierdzenie zapisu na warsztat')
            ->assertSee('Przypomnienie o warsztacie')
            ->assertSee('Anulowanie zapisu na warsztat')
            ->assertSee('Termin warsztatu się zapełnił')
            ->assertSee('Potwierdzenie zamówienia indywidualnego')
            ->assertSee('Link do płatności — zamówienie indywidualne')
            ->assertSee('Vouchery w PDF')
            ->assertSee('Potwierdzenie zgłoszenia do wypału')
            ->assertSee('placeholder="Do zobaczenia w pracowni · {dzien}, {godzina}"', false)
            // A cancellation's refund is a promise in the code, so it has no closing sentence to rewrite.
            ->assertDontSee('name="mail_booking_cancelled_outro"', false)
            ->assertDontSee('name="mail_booking_reminder_subject_en"', false);
    }

    public function test_a_default_is_worded_for_one_or_many_and_kasias_own_text_goes_out_for_both(): void
    {
        $booking = BookingConfirmed::sample('pl')->booking;
        $booking->seats = 1;
        $this->assertStringContainsString('jest opłacony — miejsce jest Twoje.', (new BookingConfirmed($booking))->render());
        $booking->seats = 2;
        $this->assertStringContainsString('jest opłacony — miejsca są Wasze.', (new BookingConfirmed($booking))->render());

        $one = VouchersIssued::sample('pl');
        $many = new VouchersIssued($one->order, $one->vouchers->concat([(clone $one->vouchers->first())->forceFill(['code' => 'MA9X4T'])]));
        $this->assertSame('Voucher MA7K2Q', $one->envelope()->subject);
        $this->assertSame('Vouchery z MellowAury', $many->envelope()->subject);

        Setting::create(['key' => 'mail_vouchers_subject', 'value' => 'Prezent z pracowni: {kod}']);
        Setting::create(['key' => 'mail_booking_confirmed_intro', 'value' => 'Zapis {numer} na {termin} — do zobaczenia!']);
        app(Settings::class)->refresh();

        $this->assertSame('Prezent z pracowni: MA7K2Q, MA9X4T', $many->envelope()->subject);
        $this->assertStringContainsString('na <strong style="font-weight: 600;">'.e($booking->workshopDate->dayLabel().', '.$booking->workshopDate->timeLabel()).'</strong> — do zobaczenia!', (new BookingConfirmed($booking))->render());
    }

    public function test_previewing_a_workshop_mail_without_the_studio_address_raises_no_alert(): void
    {
        config(['monitoring.alert_email' => 'alerty@example.com']);
        Mail::fake();

        $this->actingAs(User::factory()->create())
            ->put('/panel/maile/podglad', ['preview' => 'booking_reminder:pl', 'mail_booking_reminder_intro' => 'Jutro lepimy!'])
            ->assertOk()
            ->assertSee('Jutro lepimy!')
            ->assertSee('Adres pracowni wyślę Ci osobnym mailem');

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_every_registered_mail_renders_its_sample_in_each_language(): void
    {
        foreach (MailTexts::mails() as $key => $mail) {
            foreach ($mail->locales as $locale) {
                $class = MailTexts::mailClass($key);
                $html = $class::sample($locale)->locale($locale)->render();

                $this->assertStringContainsString('Kasia', $html, $key.' '.$locale);
            }
        }

        $this->assertArrayHasKey('withdrawal_confirmed', MailTexts::mails());
        $this->assertSame(WithdrawalConfirmed::class, MailTexts::mailClass('withdrawal_confirmed'));
    }
}
