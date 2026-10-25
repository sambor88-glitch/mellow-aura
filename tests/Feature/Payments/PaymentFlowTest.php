<?php

namespace Tests\Feature\Payments;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentState;
use App\Modules\Payments\Gateways\TestGateway;
use App\Modules\Payments\Support\StartedPayment;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The half of the payment the customer sees: the gateway takes the payment, and the shop waits
 * with the cart until somebody says the money is in.
 */
class PaymentFlowTest extends TestCase
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

    public function test_until_przelewy24_is_connected_the_stand_in_takes_the_payment(): void
    {
        $this->assertInstanceOf(TestGateway::class, app(PaymentGateway::class));
    }

    public function test_the_checkout_loads_nothing_from_stripe(): void
    {
        $this->cartWithAVase();

        $this->get('/zamowienie')
            ->assertOk()
            ->assertSee('BLIK')
            ->assertSee('Przelew tradycyjny')
            ->assertDontSee('js.stripe.com', false);
    }

    public function test_the_cart_waits_until_the_payment_is_confirmed(): void
    {
        $this->gatewayThat(start: StartedPayment::done(), state: PaymentState::Pending);
        $variant = $this->cartWithAVase();

        $this->post('/zamowienie', $this->form())
            ->assertRedirect(route('checkout.confirmation'));

        // Nothing has been paid yet, so the shelf and the cart stay exactly as they were.
        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->get('/zamowienie')->assertOk()->assertSee('Wazony');
    }

    public function test_coming_back_to_the_shop_settles_the_payment_even_before_the_gateway_calls(): void
    {
        $this->gatewayThat(start: StartedPayment::done(), state: PaymentState::Succeeded);
        $variant = $this->cartWithAVase();
        $this->post('/zamowienie', $this->form())->assertRedirect(route('checkout.confirmation'));

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
        $this->gatewayThat(start: StartedPayment::done(), state: PaymentState::Failed);
        $variant = $this->cartWithAVase();
        $this->post('/zamowienie', $this->form())->assertRedirect(route('checkout.confirmation'));

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
     * A gateway that answers whatever the test needs, so no test talks to a real provider.
     */
    private function gatewayThat(StartedPayment $start, PaymentState $state): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new class($start, $state) implements PaymentGateway
        {
            public function __construct(private StartedPayment $start, private PaymentState $state) {}

            public function start(Order $order, ?string $blikCode): StartedPayment
            {
                $order->update(['payment_provider_id' => 'test_payment']);

                return $this->start;
            }

            public function chargeBlik(Order $order, string $blikCode): StartedPayment
            {
                return $this->start;
            }

            public function takesBlikCode(): bool
            {
                return true;
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
