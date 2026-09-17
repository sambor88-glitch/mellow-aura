<?php

namespace Tests\Feature\Payments;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Models\PaymentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Stripe's call is the only word that counts about money: the browser can be closed, be lied to or
 * never come back.
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['services.stripe.webhook_secret' => self::SECRET]);
    }

    public function test_a_confirmed_payment_takes_the_pieces_off_the_shelf_and_writes_the_call_down(): void
    {
        $variant = $this->variant(stock: 3);
        $order = $this->order($variant, quantity: 2);

        $this->send($this->event())->assertNoContent();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertSame('pi_test', $order->payment_provider_id);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(1, $variant->fresh()->stock);

        $event = PaymentEvent::sole();
        $this->assertSame('evt_test', $event->event_id);
        $this->assertSame('payment_intent.succeeded', $event->type);
        $this->assertSame($order->id, $event->order_id);
        $this->assertSame('Zamówienie '.$order->number.' opłacone', $event->note);
        $this->assertNotNull($event->handled_at);
    }

    public function test_the_same_call_arriving_twice_is_handled_once(): void
    {
        $variant = $this->variant(stock: 3);
        $this->order($variant, quantity: 2);

        $this->send($this->event())->assertNoContent();
        $this->send($this->event())->assertNoContent();

        $this->assertSame(1, PaymentEvent::count());
        $this->assertSame(1, $variant->fresh()->stock);
    }

    public function test_a_call_with_a_wrong_signature_changes_nothing(): void
    {
        $variant = $this->variant(stock: 3);
        $order = $this->order($variant, quantity: 2);

        $this->send($this->event(), secret: 'whsec_someone_else')->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertSame(0, PaymentEvent::count());
    }

    public function test_a_payment_for_another_sum_is_not_accepted(): void
    {
        $variant = $this->variant(stock: 3);
        $order = $this->order($variant, quantity: 2);

        $this->send($this->event(amount: 100))->assertNoContent();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertStringContainsString('Kwota 100 gr inna niż w zamówieniu', PaymentEvent::sole()->note);
    }

    public function test_a_refused_payment_marks_the_order_and_leaves_the_shelf_alone(): void
    {
        $variant = $this->variant(stock: 3);
        $order = $this->order($variant, quantity: 2);

        $this->send($this->event(type: 'payment_intent.payment_failed'))->assertNoContent();

        $this->assertSame(PaymentStatus::Failed, $order->fresh()->payment_status);
        $this->assertSame(3, $variant->fresh()->stock);
    }

    public function test_a_refusal_never_undoes_a_payment_that_already_went_through(): void
    {
        $variant = $this->variant(stock: 3);
        $order = $this->order($variant, quantity: 2);

        $this->send($this->event())->assertNoContent();
        $this->send($this->event(id: 'evt_second', type: 'payment_intent.payment_failed'))->assertNoContent();

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_without_a_webhook_secret_the_address_does_not_exist(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $this->send($this->event())->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $id = 'evt_test', string $type = 'payment_intent.succeeded', int $amount = 49400): array
    {
        return [
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => ['id' => 'pi_test', 'object' => 'payment_intent', 'amount' => $amount, 'currency' => 'pln']],
        ];
    }

    /**
     * Signs the call the way Stripe does, so the controller's own check is what the test exercises.
     *
     * @param  array<string, mixed>  $event
     */
    private function send(array $event, string $secret = self::SECRET): TestResponse
    {
        $body = (string) json_encode($event);
        $timestamp = time();

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ], $body);
    }

    private function order(ProductVariant $variant, int $quantity): Order
    {
        $order = Order::create([
            'status' => OrderStatus::New,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker',
            'shipping_gross' => 1600,
            'total_gross' => 49400,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Pending,
            'payment_provider_id' => 'pi_test',
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name' => 'Wazony',
            'variant_label' => 'Niski 16 cm',
            'quantity' => $quantity,
            'unit_price_gross' => 23900,
        ]);

        return $order;
    }

    private function variant(int $stock): ProductVariant
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id]);

        return ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => 23900, 'stock' => $stock]);
    }
}
