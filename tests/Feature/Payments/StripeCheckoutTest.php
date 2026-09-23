<?php

namespace Tests\Feature\Payments;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentState;
use App\Modules\Payments\Gateways\StripeGateway;
use App\Modules\Payments\Gateways\TestGateway;
use App\Modules\Payments\Support\StartedPayment;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The half of the payment the customer sees: the browser confirms with the gateway, and the shop
 * waits with the cart until somebody says the money is in.
 */
class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);
    }

    public function test_the_keys_decide_who_takes_the_payment(): void
    {
        $this->assertInstanceOf(TestGateway::class, app(PaymentGateway::class));

        config(['services.stripe.key' => 'pk_test_x', 'services.stripe.secret' => 'sk_test_x']);

        $this->assertInstanceOf(StripeGateway::class, app(PaymentGateway::class));
    }

    public function test_with_keys_the_card_is_typed_into_stripes_own_field(): void
    {
        config(['services.stripe.key' => 'pk_test_x', 'services.stripe.secret' => 'sk_test_x']);
        $this->cartWithAVase();

        $this->get('/zamowienie')
            ->assertOk()
            ->assertSee('BLIK')
            ->assertSee('Przelew tradycyjny')
            ->assertSee('Visa, Mastercard')
            ->assertSee('x-ref="card"', false)
            ->assertSee('js.stripe.com', false);
    }

    public function test_the_browser_finishes_the_payment_and_the_cart_waits_until_it_does(): void
    {
        $this->gatewayThat(start: StartedPayment::confirmInBrowser('pi_test_secret'), state: PaymentState::Pending);
        $variant = $this->cartWithAVase();

        $this->postJson('/zamowienie', $this->form())
            ->assertOk()
            ->assertJson(['secret' => 'pi_test_secret', 'next' => route('checkout.confirmation')]);

        // Nothing has been paid yet, so the shelf and the cart stay exactly as they were.
        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->get('/zamowienie')->assertOk()->assertSee('Wazony');
    }

    public function test_coming_back_to_the_shop_settles_the_payment_even_before_stripe_calls(): void
    {
        $this->gatewayThat(start: StartedPayment::confirmInBrowser('pi_test_secret'), state: PaymentState::Succeeded);
        $variant = $this->cartWithAVase();
        $this->postJson('/zamowienie', $this->form())->assertOk();

        $this->get('/zamowienie/potwierdzenie')
            ->assertOk()
            ->assertSee('jest opłacone', false)
            ->assertSee('Zapłacone');

        $this->assertSame(PaymentStatus::Paid, Order::sole()->payment_status);
        $this->assertSame(2, $variant->fresh()->stock);
        $this->get('/zamowienie')->assertOk()->assertSee('Nic tu jeszcze nie ma');
    }

    public function test_a_payment_the_bank_refused_sends_the_customer_back_with_the_cart_untouched(): void
    {
        $this->gatewayThat(start: StartedPayment::confirmInBrowser('pi_test_secret'), state: PaymentState::Failed);
        $variant = $this->cartWithAVase();
        $this->postJson('/zamowienie', $this->form())->assertOk();

        $this->get('/zamowienie/potwierdzenie')
            ->assertRedirect('/zamowienie')
            ->assertSessionHas('checkout_notice', 'Bank nie potwierdził płatności. Koszyk czeka nietknięty — spróbuj jeszcze raz albo zapłać przelewem');

        $this->assertSame(PaymentStatus::Failed, Order::sole()->payment_status);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->get('/zamowienie')->assertOk()->assertSee('Wazony');
    }

    public function test_a_refused_payment_says_so_on_the_field_it_is_about(): void
    {
        $this->gatewayThat(start: StartedPayment::rejected('Bank odrzucił kod.'), state: PaymentState::Failed);
        $this->cartWithAVase();

        $this->from('/zamowienie')->post('/zamowienie', $this->form())
            ->assertRedirect('/zamowienie')
            ->assertSessionHasErrors(['blik_code' => 'Bank odrzucił kod.']);
    }

    /**
     * A gateway that answers whatever the test needs, so no test talks to Stripe.
     */
    private function gatewayThat(StartedPayment $start, PaymentState $state): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new class($start, $state) implements PaymentGateway
        {
            public function __construct(private StartedPayment $start, private PaymentState $state) {}

            public function start(Order $order, ?string $blikCode): StartedPayment
            {
                $order->update(['payment_provider_id' => 'pi_test']);

                return $this->start;
            }

            public function state(Order $order): PaymentState
            {
                return $this->state;
            }
        });
    }

    private function cartWithAVase(): ProductVariant
    {
        $product = Product::factory()->create([
            'name' => 'Wazony',
            'slug' => 'wazony',
            'category_id' => Category::factory()->create()->id,
            'stamp_enabled' => false,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => 3]);

        $this->postJson('/koszyk', ['variant_id' => $variant->id]);

        return $variant;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600 100 200',
            'shipping_method' => 'parcel_locker',
            'payment_method' => 'blik',
            'blik_code' => '123456',
            'accept_terms' => '1',
            'expected_total' => 25500,
            ...$overrides,
        ];
    }
}
