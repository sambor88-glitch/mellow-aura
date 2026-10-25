<?php

namespace Tests\Feature\Payments;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Monitoring\Mail\Alert;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentState;
use App\Modules\Payments\Gateways\Przelewy24Gateway;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Przelewy24\Signature;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Placing an order registers a transaction at Przelewy24 and sends the customer to pay there.
 * No test talks to Przelewy24: its answers are faked.
 */
class Przelewy24CheckoutTest extends TestCase
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
        config(['services.przelewy24' => [
            'merchant_id' => '111222',
            'pos_id' => null,
            'crc' => 'crc-test',
            'api_key' => 'reports-key',
            'sandbox' => true,
        ]]);
    }

    public function test_przelewy24_takes_the_payment_once_its_account_is_set_up_even_with_stripe_keys(): void
    {
        config(['services.stripe.key' => 'pk_test_x', 'services.stripe.secret' => 'sk_test_x']);

        $this->assertInstanceOf(Przelewy24Gateway::class, app(PaymentGateway::class));

        config(['services.przelewy24.crc' => null]);

        $this->assertNotInstanceOf(Przelewy24Gateway::class, app(PaymentGateway::class));
    }

    public function test_placing_an_order_registers_the_transaction_in_grosze_and_sends_the_customer_to_pay(): void
    {
        Http::fake(['sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response(['data' => ['token' => 'TOKEN-123'], 'responseCode' => 0])]);
        $variant = $this->cartWithAVase();

        $this->post('/zamowienie', $this->form(['payment_method' => 'online_transfer']))
            ->assertRedirect('https://sandbox.przelewy24.pl/trnRequest/TOKEN-123');

        $order = Order::sole();
        $payment = Payment::sole();

        $this->assertSame(25500, $payment->amount);
        $this->assertSame('PLN', $payment->currency);
        $this->assertSame('TOKEN-123', $payment->token);
        $this->assertSame(PaymentState::Pending, $payment->status);
        $this->assertStringStartsWith($order->number.'-', $payment->session_id);

        Http::assertSent(function (Request $request) use ($order, $payment) {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('111222:reports-key'))
                && $body['merchantId'] === 111222
                && $body['posId'] === 111222
                && $body['sessionId'] === $payment->session_id
                // An integer, never "255.00" or 255.0.
                && $body['amount'] === 25500
                && $body['currency'] === 'PLN'
                && $body['description'] === 'MellowAura '.$order->number
                && $body['email'] === 'ania@example.com'
                && $body['language'] === 'pl'
                && $body['urlReturn'] === route('checkout.confirmation')
                && $body['urlStatus'] === url('/przelewy24/status')
                && $body['sign'] === Signature::make([
                    'sessionId' => $payment->session_id,
                    'merchantId' => 111222,
                    'amount' => 25500,
                    'currency' => 'PLN',
                ], 'crc-test');
        });

        // Nothing has been paid yet: the shelf and the cart wait for Przelewy24's word.
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->get('/zamowienie')->assertOk()->assertSee('Wazony');
    }

    public function test_every_attempt_gets_its_own_session_id(): void
    {
        Http::fake(['sandbox.przelewy24.pl/*' => Http::response(['data' => ['token' => 'T'], 'responseCode' => 0])]);
        $this->cartWithAVase();
        $this->post('/zamowienie', $this->form(['payment_method' => 'online_transfer']));
        $order = Order::sole();

        app(PaymentGateway::class)->start($order, null);

        $this->assertSame(2, Payment::query()->where('order_id', $order->id)->distinct()->count('session_id'));
    }

    public function test_when_przelewy24_refuses_the_customer_stays_on_checkout_and_kasia_gets_an_alert(): void
    {
        config(['monitoring.alert_email' => 'alerts@example.com']);
        Http::fake(['sandbox.przelewy24.pl/*' => Http::response(['error' => 'Incorrect sign', 'code' => 400], 400)]);
        $this->cartWithAVase();

        $this->from('/zamowienie')->post('/zamowienie', $this->form(['payment_method' => 'online_transfer']))
            ->assertRedirect('/zamowienie')
            ->assertSessionHasErrors(['payment_method' => 'Płatność chwilowo nie działa. Spróbuj za chwilę albo zapłać przelewem.']);

        $this->assertSame(PaymentState::Failed, Payment::sole()->status);
        Mail::assertSent(Alert::class, fn (Alert $alert) => $alert->hasTo('alerts@example.com'));
        $this->get('/zamowienie')->assertOk()->assertSee('Wazony');
    }

    public function test_a_bank_transfer_never_goes_to_przelewy24(): void
    {
        Http::fake();
        $this->cartWithAVase();

        $this->post('/zamowienie', $this->form(['payment_method' => 'bank_transfer']))
            ->assertRedirect(route('checkout.confirmation'));

        Http::assertNothingSent();
        $this->assertSame(0, Payment::count());
    }

    public function test_the_stripe_field_stays_off_once_przelewy24_is_set_up(): void
    {
        config(['services.stripe.key' => 'pk_test_x', 'services.stripe.secret' => 'sk_test_x']);
        $this->cartWithAVase();

        $this->get('/zamowienie')
            ->assertOk()
            ->assertDontSee('js.stripe.com', false);
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
