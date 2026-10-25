<?php

namespace Tests\Feature\Payments;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Models\PaymentEvent;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Przelewy24's notification about a paid transaction: the only call that makes an order paid. Against a faked API.
 */
class Przelewy24NotificationTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://sandbox.przelewy24.pl/api/v1/';

    private ProductVariant $variant;

    private PaymentAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        config(['services.przelewy24' => ['merchant_id' => '11111', 'pos_id' => '22222', 'crc' => 'crc-key', 'api_key' => 'api-key', 'sandbox' => true]]);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);

        // An order paid by card: registered with Przelewy24 and waiting for its notification.
        // The verify answer is faked per test: the first matching fake wins, so it cannot be set here and replaced later.
        Http::fake([self::API.'transaction/register' => Http::response(['data' => ['token' => 'TOKEN-1'], 'responseCode' => 0])]);
        $this->variant = $this->cartWithAVase();
        $this->post('/zamowienie', $this->form());
        $this->attempt = PaymentAttempt::sole();
    }

    public function test_a_signed_notification_is_verified_and_only_then_pays_the_order(): void
    {
        $this->verifyAnswers();
        $this->notify()->assertOk()->assertContent('OK');

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === self::API.'transaction/verify'
            && $request['merchantId'] === 11111 && $request['posId'] === 22222
            && $request['sessionId'] === $this->attempt->session_id
            && $request['amount'] === 25500 && $request['currency'] === 'PLN' && $request['orderId'] === 555
            && $request['sign'] === hash('sha384', '{"sessionId":"'.$this->attempt->session_id.'","orderId":555,"amount":25500,"currency":"PLN","crc":"crc-key"}'));

        $order = Order::sole();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('555', $order->payment_provider_id);
        $this->assertSame(2, $this->variant->fresh()->stock);
        $this->assertSame([PaymentAttempt::PAID, 555], [$this->attempt->fresh()->status, $this->attempt->fresh()->provider_order_id]);
        Mail::assertQueued(OrderConfirmed::class);

        $event = PaymentEvent::sole();
        $this->assertSame(['przelewy24', 'p24-555', $order->id], [$event->provider, $event->event_id, $event->order_id]);
        $this->assertSame('Zamówienie '.$order->number.' opłacone', $event->note);
        $this->assertNotNull($event->handled_at);
    }

    public function test_the_same_notification_twice_takes_the_piece_off_the_shelf_once(): void
    {
        $this->verifyAnswers();
        $this->notify()->assertOk();
        $this->notify()->assertOk();

        $this->assertSame(2, $this->variant->fresh()->stock);
        $this->assertSame(1, PaymentEvent::count());
        Http::assertSentCount(2); // register and one verify
    }

    public function test_a_wrong_signature_is_refused_and_written_down_without_touching_the_order(): void
    {
        $this->verifyAnswers();
        $this->notify(['sign' => str_repeat('a', 96)])->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);
        $this->assertSame(3, $this->variant->fresh()->stock);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'transaction/verify'));

        $event = PaymentEvent::sole();
        $this->assertStringStartsWith('p24-rejected-', $event->event_id);
        $this->assertSame('Niepoprawny podpis — powiadomienie odrzucone', $event->note);

        // A forged call never blocks the real notification of the same transaction.
        $this->notify()->assertOk();
        $this->assertSame(PaymentStatus::Paid, Order::sole()->payment_status);
    }

    public function test_a_sum_other_than_the_one_registered_is_never_accepted(): void
    {
        $this->verifyAnswers();
        $this->notify(['amount' => 100])->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'transaction/verify'));
        $this->assertStringStartsWith('Kwota 100 PLN inna niż w próbie płatności', PaymentEvent::sole()->note);
    }

    public function test_an_unknown_session_is_refused(): void
    {
        $this->notify(['sessionId' => 'MA-1999-1-nothing'])->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);
        $this->assertSame('Płatność bez próby płatności w sklepie', PaymentEvent::sole()->note);
    }

    public function test_a_failed_verify_leaves_the_order_unpaid_until_przelewy24_tries_again(): void
    {
        $this->verifyAnswers(failFirst: true);

        $this->notify()->assertStatus(502);
        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);
        $this->assertSame(3, $this->variant->fresh()->stock);

        $this->notify()->assertOk();
        $this->assertSame(PaymentStatus::Paid, Order::sole()->payment_status);
        $this->assertSame(2, $this->variant->fresh()->stock);
    }

    public function test_the_staging_password_never_stops_przelewy24(): void
    {
        config(['staging.user' => 'mellowaura', 'staging.password' => 'secret']);
        $this->verifyAnswers();

        $this->notify()->assertOk();
    }

    private function verifyAnswers(bool $failFirst = false): void
    {
        $answers = Http::sequence();

        if ($failFirst) {
            $answers->push(['error' => 'Undefined error', 'code' => 500], 500);
        }

        Http::fake([self::API.'transaction/verify' => $answers->push(['data' => ['status' => 'success'], 'responseCode' => 0])->whenEmpty(Http::response(['data' => ['status' => 'success'], 'responseCode' => 0]))]);
    }

    /**
     * A notification as Przelewy24 sends it, signed with the fields in the documented order.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function notify(array $overrides = []): TestResponse
    {
        $fields = [
            'merchantId' => 11111,
            'posId' => 22222,
            'sessionId' => $this->attempt->session_id,
            'amount' => 25500,
            'originAmount' => 25500,
            'currency' => 'PLN',
            'orderId' => 555,
            'methodId' => 154,
            'statement' => 'p24-A1-B2-C3',
        ];
        $sign = hash('sha384', json_encode([...array_merge($fields, $overrides), 'crc' => 'crc-key'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $this->postJson('/przelewy24/powiadomienie', [...$fields, 'sign' => $sign, ...$overrides]);
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
     * @return array<string, mixed>
     */
    private function form(): array
    {
        return [
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600 100 200',
            'shipping_method' => 'parcel_locker',
            'payment_method' => 'card',
            'accept_terms' => '1',
            'expected_total' => 25500,
        ];
    }
}
