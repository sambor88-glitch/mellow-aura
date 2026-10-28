<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Mail\ParcelOnItsWay;
use App\Modules\Checkout\Mail\WithdrawalConfirmed;
use App\Modules\Checkout\Support\SampleOrder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Shared\Mail\MailTexts;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
