<?php

namespace Tests\Feature\Checkout;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Enums\WithdrawalScope;
use App\Modules\Checkout\Mail\NewWithdrawalReceived;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Mail\WithdrawalConfirmed;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\Withdrawal;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-20 18:32:00'));
    }

    public function test_every_page_links_to_the_form_which_asks_only_what_the_law_needs(): void
    {
        $this->get('/kontakt')->assertSeeInOrder(['href="'.route('withdrawal.create').'"', 'Odstąp od umowy tutaj'], false);
        $this->get('/wysylka-i-pielegnacja')->assertSeeInOrder(['Wysyłka i zwroty', 'href="'.route('withdrawal.create').'"', 'Odstąp od umowy tutaj'], false);

        $this->get('/odstapienie-od-umowy?zamowienie=MA-2026-1047')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('Na odstąpienie od umowy masz 14 dni od odebrania paczki, bez podawania przyczyny.')
            ->assertSeeInOrder(['Imię i nazwisko', 'E-mail', 'Numer zamówienia', 'value="MA-2026-1047"', 'Od całego zamówienia', 'Od części zamówienia', 'Które rzeczy?'], false)
            ->assertSee('>Potwierdź odstąpienie od umowy</button>', false);
    }

    public function test_a_statement_is_saved_with_its_order_and_confirmed_with_the_date_and_time(): void
    {
        Mail::fake();
        $this->settings(['contact_email' => 'kasia@example.com', 'return_address' => 'Paczkomat KRA01M']);
        $order = $this->order('Ania@Example.com');

        $this->post('/odstapienie-od-umowy', $this->form(['order_number' => 'ma 2026 '.(1000 + $order->id), 'items' => 'to zostaje pominięte']))
            ->assertRedirect('/odstapienie-od-umowy/potwierdzenie');

        $withdrawal = Withdrawal::sole();
        $this->assertSame($order->id, $withdrawal->order_id);
        $this->assertSame($order->number, $withdrawal->order_number);
        $this->assertSame(WithdrawalScope::Whole, $withdrawal->scope);
        $this->assertNull($withdrawal->items);
        $this->assertSame('2026-11-20 18:32', $withdrawal->submitted_at->format('Y-m-d H:i'));

        $this->get('/odstapienie-od-umowy/potwierdzenie')
            ->assertOk()
            ->assertSee('Dostałam Twoje oświadczenie')
            ->assertSee('Doszło 20 listopada 2026, 18:32.')
            ->assertSee('Ja, Anna Nowak, odstępuję od umowy zawartej w zamówieniu '.$order->number.'.')
            ->assertSee('Rzeczy odeślij w ciągu 14 dni od dziś na adres: Paczkomat KRA01M.');

        Mail::assertQueued(WithdrawalConfirmed::class, fn (WithdrawalConfirmed $mail) => $mail->hasTo('ania@example.com', 'Anna Nowak')
            && $mail->hasReplyTo('kasia@example.com')
            && $mail->hasSubject('Potwierdzenie odstąpienia od umowy — '.$order->number));
        Mail::assertQueued(NewWithdrawalReceived::class, fn (NewWithdrawalReceived $mail) => $mail->hasTo('kasia@example.com')
            && $mail->hasReplyTo('ania@example.com', 'Anna Nowak'));

        $mail = new WithdrawalConfirmed($withdrawal);
        $mail->assertSeeInHtml('doszło do mnie 20 listopada 2026, 18:32');
        $mail->assertSeeInHtml('Ja, Anna Nowak, odstępuję od umowy zawartej w zamówieniu '.$order->number.'.');
        $mail->assertSeeInText('Wysłane: 20 listopada 2026, 18:32');
        $mail->assertSeeInText('na adres: Paczkomat KRA01M');

        (new NewWithdrawalReceived($withdrawal->load('order')))
            ->assertSeeInHtml(route('admin.orders.show', $order))
            ->assertDontSeeInHtml('Nie znalazłam zamówienia');
    }

    public function test_a_part_of_the_order_needs_the_items_and_a_mistake_keeps_what_was_written(): void
    {
        Mail::fake();

        $this->followingRedirects()
            ->post('/odstapienie-od-umowy', $this->form(['name' => '', 'email' => 'ania.example.com', 'scope' => 'part', 'items' => '']))
            ->assertSee('Wpisz imię i nazwisko — tak jak w zamówieniu')
            ->assertSee('Adres e-mail bez małpy — sprawdź, czy nie uciekła')
            ->assertSee('Napisz, od których rzeczy odstępujesz')
            ->assertSee('value="ania.example.com"', false)
            ->assertSee('value="part" checked', false);

        $this->assertSame(0, Withdrawal::count());
        Mail::assertNothingOutgoing();

        $this->post('/odstapienie-od-umowy', $this->form(['scope' => 'part', 'items' => "Kubek „Królowa matka”\n<b>Talerz</b>"]));

        $this->get('/odstapienie-od-umowy/potwierdzenie')
            ->assertSee('Ja, Anna Nowak, odstępuję od umowy w części dotyczącej tych rzeczy z zamówienia MA-2026-1047: Kubek „Królowa matka”')
            ->assertSee('&lt;b&gt;Talerz&lt;/b&gt;', false)
            ->assertDontSee('<b>Talerz</b>', false);
    }

    public function test_a_statement_that_matches_no_order_still_counts_and_kasia_is_told_to_check_it(): void
    {
        Mail::fake();
        $this->settings(['contact_email' => 'kasia@example.com']);
        $order = $this->order('ania@example.com');

        $this->post('/odstapienie-od-umowy', $this->form(['order_number' => $order->number, 'email' => 'ktos-inny@example.com']))
            ->assertRedirect('/odstapienie-od-umowy/potwierdzenie');

        $withdrawal = Withdrawal::sole();
        $this->assertNull($withdrawal->order_id);
        Mail::assertQueued(WithdrawalConfirmed::class, fn (WithdrawalConfirmed $mail) => $mail->hasTo('ktos-inny@example.com'));

        (new NewWithdrawalReceived($withdrawal))
            ->assertSeeInHtml('Nie znalazłam zamówienia '.$order->number.' złożonego z adresu ktos-inny@example.com.')
            ->assertSeeInHtml(route('admin.withdrawals.index'));
    }

    public function test_a_mail_that_fails_is_reported_and_the_statement_stays(): void
    {
        Exceptions::fake();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Serwer poczty nie odpowiada'));

        $this->followingRedirects()
            ->post('/odstapienie-od-umowy', $this->form())
            ->assertOk()
            ->assertSee('Dostałam Twoje oświadczenie');

        $this->assertSame(1, Withdrawal::count());
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Serwer poczty nie odpowiada');
    }

    public function test_after_five_statements_in_an_hour_the_form_explains_and_keeps_the_data(): void
    {
        Mail::fake();
        $this->settings(['contact_email' => 'kasia@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/odstapienie-od-umowy', $this->form());
        }

        $this->followingRedirects()
            ->post('/odstapienie-od-umowy', $this->form(['name' => 'Szósta Osoba']))
            ->assertSee('Z tego urządzenia przyszło w ostatniej godzinie kilka oświadczeń.')
            ->assertSee('albo od razu mailem na kasia@example.com')
            ->assertSee('value="Szósta Osoba"', false);

        $this->assertSame(5, Withdrawal::count());
    }

    public function test_the_confirmation_page_without_a_statement_leads_to_the_form(): void
    {
        $this->get('/odstapienie-od-umowy/potwierdzenie')->assertRedirect('/odstapienie-od-umowy');
    }

    public function test_the_order_confirmation_links_to_the_form_with_the_order_number(): void
    {
        $order = $this->order('ania@example.com');
        $link = route('withdrawal.create', ['zamowienie' => $order->number]);

        $mail = new OrderConfirmed($order->load('items'));
        $mail->assertSeeInHtml('href="'.e($link).'"', false);
        $mail->assertSeeInText('„Odstąp od umowy tutaj”: '.$link);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->where('key', $key)->update(['value' => json_encode($value)]);
        }
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'order_number' => 'MA-2026-1047',
            'scope' => 'whole',
            ...$overrides,
        ];
    }

    private function order(string $email): Order
    {
        $order = Order::create([
            'status' => OrderStatus::InProgress,
            'name' => 'Anna Nowak',
            'email' => $email,
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker',
            'shipping_gross' => 1600,
            'total_gross' => 25500,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);
        $order->items()->create(['product_name' => 'Wazony', 'variant_label' => 'Niski 16 cm', 'quantity' => 1, 'unit_price_gross' => 23900]);

        return $order;
    }
}
