<?php

namespace Tests\Feature\CustomOrders;

use App\Models\User;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\SimulatePayment;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Enums\PaymentLinkStatus;
use App\Modules\CustomOrders\Mail\PaymentLinkSent;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\CustomOrders\Models\PaymentLink;
use App\Modules\Monitoring\Mail\Alert;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The payment link for a custom order (MA-144): Kasia sends all of the quote or a part of it from the panel, the
 * customer pays it like the shop — an order in Checkout with one item and nothing to deliver — and the payment moves
 * the idea on in the same transaction. Without Przelewy24's keys the stand-in gateway pays; its BLIK codes are in
 * SimulatePayment.
 */
class PaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Warsaw'));
    }

    public function test_kasia_sends_a_link_for_the_whole_quote_and_the_idea_waits_for_the_money(): void
    {
        $idea = $this->idea(['quote' => 90000, 'status' => CustomOrderStatus::Quoting]);

        $this->post(route('admin.custom-orders.payment-links.store', $idea), ['amount' => '900'])->assertRedirect('/panel/logowanie');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.custom-orders.index'))
            ->assertSee('Do zapłaty teraz (zł) — zostało 900,00 zł')
            ->assertSee('value="900"', false)
            ->assertSee('Połowa')
            ->assertSee('Wyślij link do płatności');

        $this->post(route('admin.custom-orders.payment-links.store', $idea), ['amount' => '900,00'])
            ->assertRedirect(route('admin.custom-orders.index').'#pomysl-'.$idea->id)
            ->assertSessionHas('panel_status', 'Link na 900,00 zł poszedł do ola@example.com.');

        $link = PaymentLink::sole();
        $this->assertSame([90000, PaymentLinkStatus::Open, CustomOrderStatus::Deposit], [$link->amount, $link->status, $idea->fresh()->status]);
        $this->assertSame('2026-11-09 23:59', $link->expires_at->format('Y-m-d H:i'));
        $this->assertSame(40, strlen($link->token));

        Mail::assertQueued(PaymentLinkSent::class, function (PaymentLinkSent $mail) use ($link) {
            $mail->assertSeeInHtml($link->url())
                ->assertSeeInHtml('Płacę 900,00 zł')
                ->assertSeeInText('Link jest ważny do 9 listopada.')
                // The quote is the amount, so it is not repeated.
                ->assertDontSeeInHtml('Wycena');

            return $mail->hasTo('ola@example.com') && $mail->envelope()->subject === 'Link do płatności: 900,00 zł';
        });

        $this->get(route('admin.custom-orders.index'))
            ->assertSee('Czeka do 9.11')
            ->assertSee($link->url());
    }

    public function test_the_amount_stays_within_what_is_left_of_the_quote(): void
    {
        $this->actingAs(User::factory()->create());
        $idea = $this->idea(['quote' => 90000]);

        foreach ([['901', 'Do zapłaty zostało 900,00 zł — wpisz tyle albo mniej'], ['0,50', 'Link musi być na co najmniej 1,00 zł'], ['abc', 'Wpisz kwotę do zapłaty, np. 450 albo 450,50']] as [$amount, $error]) {
            $this->post(route('admin.custom-orders.payment-links.store', $idea), ['amount' => $amount])
                ->assertRedirect(route('admin.custom-orders.index').'#pomysl-'.$idea->id)
                ->assertSessionHasErrorsIn('pomysl', ['amount' => $error]);
        }

        $this->post(route('admin.custom-orders.payment-links.store', $this->idea()), ['amount' => '100'])
            ->assertSessionHasErrorsIn('pomysl', ['amount' => 'Najpierw zapisz wycenę']);
        $this->post(route('admin.custom-orders.payment-links.store', $this->idea(['quote' => 90000, 'status' => CustomOrderStatus::Declined])), ['amount' => '100'])
            ->assertSessionHasErrorsIn('pomysl', ['amount' => 'Pomysł jest odrzucony — zmień status, zanim wyślesz link']);

        $this->assertSame(0, PaymentLink::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_the_customer_pays_with_blik_and_the_work_starts(): void
    {
        $link = $this->sendLink($this->idea(['quote' => 90000]), 90000);
        $this->withSession(['cart' => ['wrap' => ['type' => 'gift_wrap', 'quantity' => 1]]]);

        $this->get($link->url())
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex', false)
            ->assertSee('Kubki z napisem')
            ->assertSee('value="blik"', false)
            ->assertSee('value="card"', false)
            ->assertDontSee('value="bank_transfer"', false)
            ->assertSee('Płacę 900,00 zł');

        $this->post(route('custom-orders.payment.store', $link->token), $this->form())
            ->assertRedirect(route('custom-orders.payment.confirmation'));

        $order = Order::sole();
        $this->assertSame([PaymentStatus::Paid, OrderStatus::Completed, ShippingMethods::EMAIL, 0, 90000, 'PLN'], [
            $order->payment_status, $order->status, $order->shipping_method, $order->shipping_gross, $order->total_gross, $order->currency,
        ]);
        $this->assertSame(['Ola', 'ola@example.com', '600100200'], [$order->name, $order->email, $order->phone]);
        $this->assertSame(['Zamówienie indywidualne: Kubki z napisem', 'Wpłata do wyceny 900,00 zł', 1, 90000, true], [
            $order->items[0]->product_name, $order->items[0]->variant_label, $order->items[0]->quantity, $order->items[0]->unit_price_gross, $order->items[0]->is_made_to_order,
        ]);
        $this->assertNotNull($order->terms_accepted_at);
        $this->assertSame([PaymentLinkStatus::Paid, CustomOrderStatus::InProgress], [$link->fresh()->status, $link->customOrderRequest->fresh()->status]);

        $this->get(route('custom-orders.payment.confirmation'))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Dziękuję, zaczynam')
            ->assertSee($order->number)
            ->assertSee('900,00 zł');

        // Przelewy24 sends everyone back to the shop's confirmation; a payment for an idea moves on to its own.
        $this->get(route('checkout.confirmation'))->assertRedirect(route('custom-orders.payment.confirmation'));
        $this->assertSame(['wrap' => ['type' => 'gift_wrap', 'quantity' => 1]], session('cart'));

        $this->get($link->url())->assertSee('Ta wpłata już jest')->assertDontSee('Płacę 900,00 zł');
        $this->actingAs(User::factory()->create())
            ->get(route('admin.custom-orders.index', ['status' => 'in_progress']))
            ->assertSee('Opłacony 26.10')
            ->assertSee('Wycena opłacona w całości.')
            ->assertDontSee('Wyślij link do płatności');
    }

    public function test_a_big_order_is_paid_in_two_halves(): void
    {
        $idea = $this->idea(['quote' => 240000]);
        $this->pay($this->sendLink($idea, 120000));

        $this->assertSame([CustomOrderStatus::InProgress, 120000], [$idea->fresh()->status, $idea->fresh()->amountDue()]);
        $this->actingAs(User::factory()->create())
            ->get(route('admin.custom-orders.index'))
            ->assertSee('zostało 1200,00 zł')
            ->assertSee('value="1200"', false);

        $this->flushSession();
        $rest = $this->sendLink($idea, 120000);
        Mail::assertQueued(PaymentLinkSent::class, fn (PaymentLinkSent $mail) => $mail->link->is($rest) && str_contains($mail->render(), 'Wycena'));

        $this->pay($rest);

        $this->assertSame([CustomOrderStatus::InProgress, 0], [$idea->fresh()->status, $idea->fresh()->amountDue()]);
        $this->assertSame([OrderStatus::Completed, OrderStatus::Completed], Order::query()->orderBy('id')->pluck('status')->all());
    }

    public function test_a_refused_code_keeps_the_link_open(): void
    {
        $link = $this->sendLink($this->idea(['quote' => 90000]), 90000);

        $this->from($link->url())
            ->post(route('custom-orders.payment.store', $link->token), $this->form(['blik_code' => SimulatePayment::REJECTED_BLIK_CODE]))
            ->assertRedirect($link->url())
            ->assertSessionHasErrors('blik_code');

        $this->assertSame(PaymentStatus::Failed, Order::sole()->payment_status);
        $this->assertSame([PaymentLinkStatus::Open, CustomOrderStatus::Deposit], [$link->fresh()->status, $link->customOrderRequest->fresh()->status]);
        $this->get($link->url())->assertSee('Płacę 900,00 zł');
    }

    public function test_a_code_waiting_for_the_bank_goes_to_the_blik_page_and_back_to_the_link(): void
    {
        $link = $this->sendLink($this->idea(['quote' => 90000]), 90000);

        $this->post(route('custom-orders.payment.store', $link->token), $this->form(['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]))
            ->assertRedirect(route('checkout.blik'));

        $this->travel(3)->minutes();
        $this->get(route('checkout.blik'))
            ->assertOk()
            ->assertSee('wpłata <strong class="font-medium select-all">'.Order::sole()->number.'</strong> czeka', false)
            ->assertSee('href="'.$link->url().'"', false);

        // A failed payment goes back to the link, not to the cart.
        Order::sole()->update(['payment_status' => PaymentStatus::Failed]);
        $this->get(route('checkout.confirmation'))->assertRedirect(route('custom-orders.payment.confirmation'));
        $this->get(route('custom-orders.payment.confirmation'))
            ->assertRedirect($link->url())
            ->assertSessionHas('payment_notice');
    }

    public function test_a_replaced_or_expired_link_takes_no_payment(): void
    {
        $idea = $this->idea(['quote' => 90000]);
        $old = $this->sendLink($idea, 90000);
        $new = $this->sendLink($idea, 45000);

        $this->assertSame([PaymentLinkStatus::Cancelled, PaymentLinkStatus::Open], [$old->fresh()->status, $new->fresh()->status]);
        $this->get($old->url())->assertOk()->assertSee('Ten link jest już nieaktualny')->assertDontSee('Płacę');
        $this->post(route('custom-orders.payment.store', $old->token), $this->form())->assertRedirect($old->url());

        $this->travel(PaymentLink::VALID_DAYS + 1)->days();
        $this->get($new->url())->assertSee('Ten link jest już nieaktualny');
        $this->post(route('custom-orders.payment.store', $new->token), $this->form())->assertRedirect($new->url());

        $this->assertSame(0, Order::query()->count());
        $this->get('/zamowienia-indywidualne/platnosc/'.str_repeat('a', 40))->assertNotFound();
    }

    public function test_money_above_the_quote_puts_the_order_under_problem_and_kasia_hears_about_it(): void
    {
        config(['monitoring.alert_email' => 'alerty@example.com']);
        $idea = $this->idea(['quote' => 90000]);
        $old = $this->sendLink($idea, 90000);

        // A payment on the first link waits for the bank while Kasia sends a new one, and both come in.
        $this->post(route('custom-orders.payment.store', $old->token), $this->form(['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]));
        $late = Order::sole();
        $this->flushSession();
        $this->pay($this->sendLink($idea, 90000));

        app(MarkOrderPaid::class)($late, 'test-late');

        $this->assertSame([PaymentStatus::Paid, OrderStatus::Problem], [$late->fresh()->payment_status, $late->fresh()->status]);
        $this->assertSame('Wpłaty przekraczają wycenę 900,00 zł. Zwróć nadpłatę.', $late->fresh()->problem_note);
        $this->assertSame([PaymentLinkStatus::Paid, 180000], [$old->fresh()->status, $idea->fresh()->paidAmount()]);
        Mail::assertSent(Alert::class, fn (Alert $alert) => $alert->hasTo('alerty@example.com'));

        // The notification arriving again changes nothing.
        app(MarkOrderPaid::class)($late->fresh(), 'test-late');
        $this->assertSame(180000, $idea->fresh()->paidAmount());
    }

    public function test_a_payment_for_a_declined_idea_is_a_problem(): void
    {
        config(['monitoring.alert_email' => 'alerty@example.com']);
        $idea = $this->idea(['quote' => 90000]);
        $link = $this->sendLink($idea, 90000);

        $this->post(route('custom-orders.payment.store', $link->token), $this->form(['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]));
        $idea->update(['status' => CustomOrderStatus::Declined]);
        app(MarkOrderPaid::class)(Order::sole(), 'test-late');

        $this->assertSame(OrderStatus::Problem, Order::sole()->status);
        $this->assertStringStartsWith('Wpłata za odrzucony pomysł', Order::sole()->problem_note);
        $this->assertSame(CustomOrderStatus::Declined, $idea->fresh()->status);
        Mail::assertSent(Alert::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function idea(array $attributes = []): CustomOrderRequest
    {
        return CustomOrderRequest::query()->create([
            'name' => 'Ola',
            'email' => 'ola@example.com',
            'phone' => '600 100 200',
            'kind' => BriefKind::Mugs,
            'description' => 'Kubki dla świadków',
            'status' => CustomOrderStatus::New,
            ...$attributes,
        ]);
    }

    private function sendLink(CustomOrderRequest $idea, int $amount): PaymentLink
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.custom-orders.payment-links.store', $idea), ['amount' => (string) ($amount / 100)])
            ->assertSessionHasNoErrors();

        return PaymentLink::query()->latest('id')->firstOrFail();
    }

    private function pay(PaymentLink $link): void
    {
        $this->post(route('custom-orders.payment.store', $link->token), $this->form())
            ->assertRedirect(route('custom-orders.payment.confirmation'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return ['payment_method' => 'blik', 'blik_code' => '123456', 'accept_terms' => '1', ...$overrides];
    }
}
