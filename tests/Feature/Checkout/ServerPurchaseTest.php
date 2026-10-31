<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\AnonymizeCustomer;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\AnalyticsClient;
use App\Modules\Checkout\Support\MeasurementProtocol;
use App\Modules\Consent\Support\Consent;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * The purchase told to Google Analytics from the server (MA-264).
 */
class ServerPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private const GA_COOKIES = '_ga=GA1.1.1234567890.1700000000; _ga_AB12CD34EF=GS2.1.s1761900000$o3$g1$t1761900100$j60$l0$h0';

    private ProductVariant $vase;

    /** What Google answers; a test sets it before the order is paid. */
    private int $googleStatus = 204;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
        Http::fake([MeasurementProtocol::ENDPOINT.'*' => fn () => Http::response($this->googleStatus === 204 ? null : 'nope', $this->googleStatus)]);
        config(['services.ga4.api_secret' => 'test-secret']);

        Setting::create(['key' => 'google_analytics_id', 'value' => 'G-AB12CD34EF']);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);

        $product = Product::factory()->create([
            'name' => 'Wazony', 'slug' => 'wazony', 'category_id' => Category::factory()->create()->id,
            'is_published' => true, 'stamp_enabled' => false,
        ]);
        $this->vase = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => 5]);
    }

    public function test_a_paid_order_with_consent_is_told_to_google_analytics_once_with_its_number(): void
    {
        $order = $this->order(consent: true);

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->analytics_sent_at);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($order) {
            $event = $request['events'][0];

            return str_starts_with($request->url(), MeasurementProtocol::ENDPOINT.'?')
                && str_contains($request->url(), 'measurement_id=G-AB12CD34EF')
                && str_contains($request->url(), 'api_secret=test-secret')
                && $request['client_id'] === '1234567890.1700000000'
                && $request['consent'] === ['ad_user_data' => 'DENIED', 'ad_personalization' => 'DENIED']
                && ! isset($request['user_data'])
                && $event['name'] === 'purchase'
                && $event['params']['transaction_id'] === $order->number
                && $event['params']['currency'] === 'PLN'
                && $event['params']['value'] === 478
                && $event['params']['shipping'] === 16
                && $event['params']['session_id'] === '1761900000'
                && $event['params']['debug_mode'] === true
                && $event['params']['items'][0]['item_id'] === 'v'.$this->vase->id
                && $event['params']['items'][0]['quantity'] === 2;
        });
    }

    public function test_without_consent_nothing_is_kept_and_nothing_is_sent(): void
    {
        $order = $this->order(consent: false);

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNull($order->ga_client_id);
        $this->assertNull($order->analytics_items);
        Http::assertNothingSent();
    }

    public function test_without_the_api_secret_nothing_is_sent(): void
    {
        config(['services.ga4.api_secret' => null]);

        $this->assertNull($this->order(consent: true)->analytics_sent_at);
        Http::assertNothingSent();
    }

    public function test_without_the_measurement_id_in_the_panel_nothing_is_sent(): void
    {
        Setting::query()->whereKey('google_analytics_id')->delete();

        $this->order(consent: true);

        Http::assertNothingSent();
    }

    public function test_a_repeated_confirmation_or_event_sends_no_second_purchase(): void
    {
        $order = $this->order(consent: true);

        // Przelewy24 repeats its notification; the event can also arrive twice.
        app(MarkOrderPaid::class)($order, 'p24-repeated');
        OrderPaid::dispatch($order->fresh());

        Http::assertSentCount(1);
    }

    public function test_google_failing_never_undoes_the_payment_and_reaches_the_alerts_without_the_secret(): void
    {
        Exceptions::fake();
        $this->googleStatus = 500;

        $order = $this->order(consent: true);

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(3, $this->vase->fresh()->stock);
        // Unmarked, so a later attempt can still send it.
        $this->assertNull($order->analytics_sent_at);
        Exceptions::assertReported(fn (RuntimeException $e) => str_contains($e->getMessage(), $order->number) && ! str_contains($e->getMessage(), 'test-secret'));
    }

    public function test_the_hashed_e_mail_goes_only_with_consent_to_ad_user_data(): void
    {
        $order = $this->order(consent: true);
        // The banner asks about statistics only, so a real order never carries the e-mail yet.
        $this->assertFalse($order->analytics_ad_user_data);
        $order->update(['analytics_ad_user_data' => true, 'email' => ' Anna.Nowak@Gmail.com ']);

        $payload = app(MeasurementProtocol::class)->payload($order->fresh());

        $this->assertSame(['ad_user_data' => 'GRANTED', 'ad_personalization' => 'DENIED'], $payload['consent']);
        $this->assertSame([hash('sha256', 'annanowak@gmail.com')], $payload['user_data']['sha256_email_address']);
        $this->assertStringNotContainsStringIgnoringCase('nowak', json_encode($payload));
    }

    public function test_an_english_order_is_told_in_its_currency(): void
    {
        $order = $this->order(consent: true);
        $order->update(['currency' => 'EUR', 'total_gross' => 6400, 'shipping_gross' => 1400]);

        $params = app(MeasurementProtocol::class)->payload($order->fresh())['events'][0]['params'];

        $this->assertSame(['EUR', 50, 14], [$params['currency'], $params['value'], $params['shipping']]);
    }

    public function test_the_cookies_are_read_in_both_formats_and_anything_else_is_ignored(): void
    {
        $this->assertSame('1234567890.1700000000', AnalyticsClient::clientId('GA1.1.1234567890.1700000000'));
        $this->assertNull(AnalyticsClient::clientId('GA1.1.<script>'));
        $this->assertNull(AnalyticsClient::clientId(null));
        $this->assertSame('1700000000', AnalyticsClient::sessionId('GS1.1.1700000000.1.1.1700000100.0.0.0'));
        $this->assertSame('1761900000', AnalyticsClient::sessionId('GS2.1.s1761900000$o3$g1$t1761900100'));
        $this->assertNull(AnalyticsClient::sessionId('GS9.x'));
    }

    public function test_forgetting_the_customer_forgets_her_browser_id(): void
    {
        $order = $this->order(consent: true);

        app(AnonymizeCustomer::class)('ania@example.com');

        $this->assertNull($order->fresh()->ga_client_id);
        $this->assertNull($order->fresh()->ga_session_id);
    }

    private function order(bool $consent): Order
    {
        $this->postJson('/koszyk', ['variant_id' => $this->vase->id, 'quantity' => 2]);

        $this->withCookie(Consent::COOKIE, '1.'.($consent ? 'analytics' : 'necessary').'.'.now()->timestamp)
            ->withHeader('Cookie', self::GA_COOKIES)
            ->post('/zamowienie', [
                'phone' => '600 100 200',
                'email' => 'ania@example.com',
                'name' => 'Anna Nowak',
                'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
                'payment_method' => 'blik',
                'blik_code' => '123456',
                'accept_terms' => '1',
                'expected_total' => 49400,
            ])
            ->assertRedirect('/zamowienie/potwierdzenie');

        return Order::query()->latest('id')->firstOrFail();
    }
}
