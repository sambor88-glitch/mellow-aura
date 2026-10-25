<?php

namespace Tests\Feature\Payments;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\OrderAwaitingPayment;
use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Gateways\Przelewy24Gateway;
use App\Modules\Payments\Gateways\TestGateway;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Starting a payment with Przelewy24, against a faked API: nothing here talks to Przelewy24.
 */
class Przelewy24GatewayTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://sandbox.przelewy24.pl/api/v1/';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        config(['services.przelewy24' => ['merchant_id' => '11111', 'pos_id' => '', 'crc' => 'crc-key', 'api_key' => 'api-key', 'sandbox' => true]]);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);
    }

    public function test_with_the_keys_przelewy24_takes_the_payment_and_without_them_the_stand_in(): void
    {
        $this->assertInstanceOf(Przelewy24Gateway::class, app(PaymentGateway::class));

        config(['services.przelewy24.crc' => null]);

        $this->assertInstanceOf(TestGateway::class, app(PaymentGateway::class));
    }

    public function test_a_card_payment_is_registered_in_grosze_and_finished_on_przelewy24s_page(): void
    {
        Http::fake([self::API.'transaction/register' => Http::response(['data' => ['token' => 'TOKEN-1'], 'responseCode' => 0])]);
        $variant = $this->cartWithAVase();

        $this->post('/zamowienie', $this->form(['payment_method' => 'card', 'blik_code' => '']))
            ->assertRedirect('https://sandbox.przelewy24.pl/trnRequest/TOKEN-1');

        $order = Order::sole();
        $attempt = PaymentAttempt::sole();
        $this->assertSame([25500, 'PLN', 'przelewy24', 'TOKEN-1'], [$attempt->amount, $attempt->currency, $attempt->provider, $attempt->token]);
        $this->assertStringStartsWith($order->number.'-', $attempt->session_id);
        $this->assertSame($attempt->session_id, $order->payment_provider_id);

        Http::assertSent(function (Request $request) use ($attempt, $order) {
            // posId falls back to the merchant id; Basic auth is posId and the API key.
            return $request->url() === self::API.'transaction/register'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('11111:api-key'))
                && $request['merchantId'] === 11111 && $request['posId'] === 11111
                && $request['sessionId'] === $attempt->session_id
                && $request['amount'] === 25500 && $request['currency'] === 'PLN'
                && $request['description'] === 'MellowAura '.$order->number
                && $request['email'] === 'ania@example.com' && $request['language'] === 'pl' && $request['country'] === 'PL'
                && $request['urlReturn'] === route('checkout.confirmation')
                && $request['urlStatus'] === route('payments.przelewy24.notify')
                && $request['sign'] === hash('sha384', '{"sessionId":"'.$attempt->session_id.'","merchantId":11111,"amount":25500,"currency":"PLN","crc":"crc-key"}')
                && ! isset($request['additional']);
        });

        // Nothing is paid until the notification: the shelf stays full and the customer gets the summary.
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(3, $variant->fresh()->stock);
        Mail::assertQueued(OrderAwaitingPayment::class);
    }

    public function test_blik_is_charged_with_the_code_from_our_own_field(): void
    {
        Http::fake([
            self::API.'transaction/register' => Http::response(['data' => ['token' => 'TOKEN-2'], 'responseCode' => 0]),
            self::API.'paymentMethod/blik/chargeByCode' => Http::response(['data' => ['orderId' => 987654, 'message' => 'success'], 'responseCode' => 0], 201),
        ]);
        $this->cartWithAVase();

        $this->withHeader('User-Agent', 'Firefox')->post('/zamowienie', $this->form())
            ->assertRedirect(route('checkout.blik'));

        Http::assertSent(fn (Request $request) => $request->url() === self::API.'transaction/register'
            && $request['additional']['PSU']['userAgent'] === 'Firefox' && filled($request['additional']['PSU']['IP']));
        Http::assertSent(fn (Request $request) => $request->url() === self::API.'paymentMethod/blik/chargeByCode'
            && $request['token'] === 'TOKEN-2' && $request['blikCode'] === '123456');

        $this->assertSame(987654, PaymentAttempt::sole()->provider_order_id);
        // The customer confirms in the bank's app; until the notification the order waits.
        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);
    }

    public function test_a_blik_code_przelewy24_refuses_says_so_on_the_code_field_and_keeps_the_cart(): void
    {
        Http::fake([
            self::API.'transaction/register' => Http::response(['data' => ['token' => 'TOKEN-3'], 'responseCode' => 0]),
            self::API.'paymentMethod/blik/chargeByCode' => Http::response(['error' => 'Invalid input data', 'code' => 400], 400),
        ]);
        $this->cartWithAVase();

        $this->from('/zamowienie')->post('/zamowienie', $this->form())
            ->assertRedirect('/zamowienie')
            ->assertSessionHasErrors(['blik_code' => 'Bank odrzucił kod. Spróbuj jeszcze raz albo zapłać przelewem.']);

        $this->assertSame(PaymentAttempt::FAILED, PaymentAttempt::sole()->status);
        Mail::assertNotQueued(OrderAwaitingPayment::class);
        $this->get('/zamowienie')->assertOk()->assertSee('Wazony');
    }

    public function test_przelewy24_down_is_said_on_the_payment_field(): void
    {
        Http::fake([self::API.'transaction/register' => Http::response(['error' => 'Undefined error', 'code' => 500], 500)]);
        $this->cartWithAVase();

        $this->from('/zamowienie')->post('/zamowienie', $this->form(['payment_method' => 'online_transfer', 'blik_code' => '']))
            ->assertRedirect('/zamowienie')
            ->assertSessionHasErrors(['payment_method' => 'Płatność chwilowo nie działa. Spróbuj za chwilę albo zapłać przelewem.']);

        $this->assertSame(0, PaymentAttempt::count());
    }

    public function test_a_bank_transfer_registers_nothing_and_waits_for_the_money(): void
    {
        Http::fake();
        $this->cartWithAVase();

        $this->post('/zamowienie', $this->form(['payment_method' => 'bank_transfer', 'blik_code' => '']))
            ->assertRedirect(route('checkout.confirmation'));

        Http::assertNothingSent();
        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);
        Mail::assertQueued(OrderAwaitingPayment::class);
    }

    public function test_back_in_the_shop_only_a_verified_payment_counts(): void
    {
        Http::fake([
            self::API.'transaction/register' => Http::response(['data' => ['token' => 'TOKEN-4'], 'responseCode' => 0]),
            self::API.'transaction/by/sessionId/*' => Http::sequence()
                ->push(['data' => ['status' => 1], 'responseCode' => 0])
                ->push(['data' => ['status' => 2], 'responseCode' => 0]),
        ]);
        $variant = $this->cartWithAVase();
        $this->post('/zamowienie', $this->form(['payment_method' => 'card', 'blik_code' => '']));

        // Status 1 is money Przelewy24 holds but nobody verified yet: the order keeps waiting for the notification.
        $this->get('/zamowienie/potwierdzenie')->assertOk();
        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);
        $this->assertSame(3, $variant->fresh()->stock);

        $this->get('/zamowienie/potwierdzenie')->assertOk()->assertSee('Zapłacone');
        $this->assertSame(PaymentStatus::Paid, Order::sole()->payment_status);
        $this->assertSame(2, $variant->fresh()->stock);
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
