<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\SimulatePayment;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\OrderAwaitingPayment;
use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The BLIK page (MA-101): the clock while the customer confirms in the bank's app, and a new code for the same
 * order when it runs out. With the stand-in gateway the code 111111 is one nobody confirms.
 */
class BlikPageTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://sandbox.przelewy24.pl/api/v1/';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);
    }

    public function test_a_taken_code_waits_on_the_blik_page_with_the_clock(): void
    {
        $variant = $this->cartWithAVase();

        $this->post('/zamowienie', $this->form(['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]))
            ->assertRedirect(route('checkout.blik'));

        $this->get('/zamowienie/blik')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Potwierdź płatność w aplikacji banku')
            ->assertSee('2:00')
            ->assertSee(Order::sole()->number)
            ->assertSee('<meta http-equiv="refresh" content="5">', false);

        // Nothing is paid yet: the shelf and the cart stay, and the confirmation sends the customer back to the clock.
        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->get('/zamowienie/potwierdzenie')->assertRedirect(route('checkout.blik'));
        $this->get('/zamowienie')->assertSee('Wazony');
        $this->getJson('/zamowienie/blik/stan')->assertExactJson(['paid' => false, 'url' => null]);
    }

    public function test_the_page_moves_on_once_the_payment_is_in(): void
    {
        $this->cartWithAVase();
        $this->post('/zamowienie', $this->form(['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]));

        // The notification marks the order paid while the page is asking.
        Order::sole()->update(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->getJson('/zamowienie/blik/stan')->assertExactJson(['paid' => true, 'url' => route('checkout.confirmation')]);
        $this->get('/zamowienie/blik')->assertRedirect(route('checkout.confirmation'));
    }

    public function test_after_two_minutes_a_new_code_pays_the_same_order(): void
    {
        $variant = $this->cartWithAVase();
        $this->post('/zamowienie', $this->form(['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]));

        // While the first code can still be confirmed, a second one is not charged.
        $this->post('/zamowienie/blik', ['blik_code' => '123456'])->assertRedirect(route('checkout.blik'));
        $this->assertSame(PaymentStatus::Pending, Order::sole()->payment_status);

        $this->travel(121)->seconds();

        $this->get('/zamowienie/blik')
            ->assertOk()
            ->assertSee('Kod wygasł')
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('6 cyfr, ważne 2 minuty')
            ->assertSee('Płacę 255,00 zł')
            ->assertDontSee('http-equiv="refresh"', false);

        $this->post('/zamowienie/blik', ['blik_code' => '123 456'])->assertRedirect(route('checkout.confirmation'));

        $this->assertSame(1, Order::count());
        $this->assertSame(PaymentStatus::Paid, Order::sole()->payment_status);
        $this->assertSame(2, $variant->fresh()->stock);
        // One order summary, however many codes it took.
        Mail::assertQueued(OrderAwaitingPayment::class, 1);
    }

    public function test_a_refused_new_code_asks_for_another_and_keeps_the_cart(): void
    {
        $this->cartWithAVase();
        $this->post('/zamowienie', $this->form(['blik_code' => SimulatePayment::UNCONFIRMED_BLIK_CODE]));
        $this->travel(121)->seconds();

        $this->from('/zamowienie/blik')->followingRedirects()
            ->post('/zamowienie/blik', ['blik_code' => SimulatePayment::REJECTED_BLIK_CODE])
            ->assertOk()
            ->assertSee('Bank nie przyjął kodu')
            ->assertSee('Bank odrzucił kod. Spróbuj jeszcze raz albo zapłać przelewem.')
            ->assertSee('aria-invalid="true"', false);

        $this->post('/zamowienie/blik', ['blik_code' => '12'])->assertSessionHasErrors(['blik_code' => 'Wpisz 6-cyfrowy kod z aplikacji banku']);
        $this->get('/zamowienie')->assertSee('Wazony');

        // The same order takes the next code.
        $this->post('/zamowienie/blik', ['blik_code' => '654321'])->assertRedirect(route('checkout.confirmation'));
        $this->assertSame(PaymentStatus::Paid, Order::sole()->payment_status);
    }

    public function test_without_an_order_in_progress_there_is_nothing_to_wait_for(): void
    {
        $this->get('/zamowienie/blik')->assertRedirect(route('shop.index'));
        $this->post('/zamowienie/blik', ['blik_code' => '123456'])->assertRedirect(route('shop.index'));
        $this->getJson('/zamowienie/blik/stan')->assertExactJson(['paid' => false, 'url' => null]);
    }

    public function test_a_new_code_with_przelewy24_is_a_new_registered_try_on_the_same_order(): void
    {
        $this->przelewy24();
        Http::fake([
            self::API.'transaction/register' => Http::sequence()
                ->push(['data' => ['token' => 'TOKEN-1'], 'responseCode' => 0])
                ->push(['data' => ['token' => 'TOKEN-2'], 'responseCode' => 0]),
            self::API.'paymentMethod/blik/chargeByCode' => Http::response(['data' => ['orderId' => 987654, 'message' => 'success'], 'responseCode' => 0], 201),
            self::API.'transaction/by/sessionId/*' => Http::response(['data' => ['status' => 0]]),
        ]);
        $this->cartWithAVase();

        $this->post('/zamowienie', $this->form())->assertRedirect(route('checkout.blik'));
        $this->travel(121)->seconds();
        $this->post('/zamowienie/blik', ['blik_code' => '222333'])->assertRedirect(route('checkout.blik'));

        $attempts = PaymentAttempt::orderBy('id')->get();
        $this->assertCount(2, $attempts);
        $this->assertSame([Order::sole()->id], $attempts->pluck('order_id')->unique()->values()->all());
        $this->assertNotSame($attempts[0]->session_id, $attempts[1]->session_id);
        $this->assertSame($attempts[1]->session_id, Order::sole()->payment_provider_id);
        Http::assertSent(fn (Request $request) => $request->url() === self::API.'paymentMethod/blik/chargeByCode'
            && $request['token'] === 'TOKEN-2' && $request['blikCode'] === '222333');

        // A fresh clock for the new code.
        $this->get('/zamowienie/blik')->assertOk()->assertSee('2:00');
    }

    public function test_without_blik_level_0_the_code_is_typed_on_przelewy24s_page(): void
    {
        $this->przelewy24(['blik_on_site' => false]);
        Http::fake([
            self::API.'transaction/register' => Http::response(['data' => ['token' => 'TOKEN-3'], 'responseCode' => 0]),
        ]);
        $this->cartWithAVase();

        $this->get('/zamowienie')->assertOk()->assertSee('BLIK')->assertDontSee('id="blik_code"', false);

        $this->post('/zamowienie', $this->form(['blik_code' => '']))
            ->assertRedirect('https://sandbox.przelewy24.pl/trnRequest/TOKEN-3');

        Http::assertSent(fn (Request $request) => $request->url() === self::API.'transaction/register'
            && $request['method'] === 154 && ! isset($request['additional']));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'chargeByCode'));
        $this->get('/zamowienie/blik')->assertRedirect(route('checkout.confirmation'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function przelewy24(array $overrides = []): void
    {
        config(['services.przelewy24' => ['merchant_id' => '11111', 'pos_id' => '', 'crc' => 'crc-key', 'api_key' => 'api-key', 'sandbox' => true, 'blik_on_site' => true, ...$overrides]]);
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
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
            'payment_method' => 'blik',
            'blik_code' => '123456',
            'accept_terms' => '1',
            'expected_total' => 25500,
            ...$overrides,
        ];
    }
}
