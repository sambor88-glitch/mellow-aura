<?php

namespace Tests\Feature\Gifts;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Enums\RedemptionStatus;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Models\VoucherRedemption;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * An amount voucher as a gift card (MA-211): its balance pays at the checkout, in parts and with other codes, and
 * never more than it holds — also when two orders use the same code at once.
 */
class VoucherBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);
        Setting::create(['key' => 'voucher_validity_months', 'value' => 12]);
    }

    public function test_a_paid_amount_voucher_starts_with_its_whole_worth_and_a_workshop_voucher_with_none(): void
    {
        $category = Category::factory()->create(['group' => CategoryGroup::Workshops]);
        $amount = ProductVariant::factory()->for(Product::factory()->state(['category_id' => $category->id, 'slug' => 'voucher-kwotowy']))->create(['price_gross' => 25000, 'stock' => null]);
        $seats = ProductVariant::factory()->for(Product::factory()->state(['category_id' => $category->id, 'slug' => 'voucher-na-kolo', 'workshop_code' => 'wheel']))->create(['price_gross' => 30000, 'stock' => null]);

        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $amount->id, 'recipient_name' => 'Ola']);
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $seats->id, 'recipient_name' => 'Ola']);
        $this->post('/zamowienie', $this->form(['expected_total' => 55000]))->assertRedirect('/zamowienie/potwierdzenie');

        $this->assertSame([[25000, 25000], [null, null]], Voucher::query()->orderBy('amount_minor', 'desc')->get()->map(fn (Voucher $voucher) => [$voucher->amount_minor, $voucher->balance_minor])->all());
    }

    public function test_a_voucher_that_covers_everything_pays_without_the_gateway_and_keeps_the_rest(): void
    {
        $voucher = $this->voucher(30000);
        $vase = $this->cartWithAVase();

        $this->post('/zamowienie/voucher', ['voucher_code' => strtolower(str_replace('-', ' ', $voucher->code)), 'email' => 'ania@example.com'])
            ->assertRedirect('/zamowienie')
            ->assertSessionHasInput('email', 'ania@example.com');

        $this->get('/zamowienie')
            ->assertSee('Voucher '.$voucher->code)
            ->assertSee('zostanie na nim 45 zł')
            ->assertSee('Zamawiam, płaci voucher')
            ->assertSee('name="expected_vouchers" value="25500"', false)
            ->assertSee('Voucher pokrywa całość');

        // No BLIK code: there is nothing left to pay.
        $this->post('/zamowienie', $this->form(['blik_code' => '', 'expected_vouchers' => 25500]))->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::sole();
        $this->assertSame([PaymentStatus::Paid, PaymentMethod::Voucher, 25500, 0, 'voucher'], [$order->payment_status, $order->payment_method, $order->vouchers_gross, $order->amountDue(), $order->payment_provider_id]);
        $this->assertSame(2, $vase->fresh()->stock);
        $this->assertSame(4500, $voucher->fresh()->balance_minor);
        $this->assertSame([[25500, RedemptionStatus::Used]], VoucherRedemption::all()->map(fn ($use) => [$use->amount_minor, $use->status])->all());

        $this->get('/zamowienie/potwierdzenie')->assertSee('Z vouchera');
        Mail::assertQueued(OrderConfirmed::class, fn (OrderConfirmed $mail) => str_contains($mail->render(), 'Voucher '.$voucher->code));
        // The cart is paid for, so the code goes with it.
        $this->assertNull(session('checkout.vouchers'));
    }

    public function test_a_voucher_that_covers_part_leaves_the_rest_for_przelewy24_and_spends_its_part_on_payment(): void
    {
        config(['services.przelewy24' => ['merchant_id' => '11111', 'pos_id' => '', 'crc' => 'crc-key', 'api_key' => 'api-key', 'sandbox' => true]]);
        Http::fake(['https://sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response(['data' => ['token' => 'TOKEN-1'], 'responseCode' => 0])]);
        $small = $this->voucher(5000);
        $other = $this->voucher(5000);
        $this->cartWithAVase();

        $this->post('/zamowienie/voucher', ['voucher_code' => $small->code]);
        $this->post('/zamowienie/voucher', ['voucher_code' => $other->code]);
        $this->post('/zamowienie/voucher', ['voucher_code' => $other->code])->assertSessionHasErrors(['voucher_code' => 'Ten voucher już odjęłam od sumy.']);

        $this->get('/zamowienie')->assertSee('Do zapłaty')->assertSee('Płacę 155 zł');
        $this->post('/zamowienie', $this->form(['payment_method' => 'card', 'blik_code' => '', 'expected_vouchers' => 10000]))
            ->assertRedirect('https://sandbox.przelewy24.pl/trnRequest/TOKEN-1');

        // Przelewy24 is asked for the rest only, and the vouchers' money waits held.
        $order = Order::sole();
        $this->assertSame([25500, 10000, 15500], [$order->total_gross, $order->vouchers_gross, PaymentAttempt::sole()->amount]);
        $this->assertSame([0, 0], [$small->fresh()->balance_minor, $other->fresh()->balance_minor]);
        $this->assertSame([RedemptionStatus::Held, RedemptionStatus::Held], VoucherRedemption::query()->orderBy('id')->pluck('status')->all());

        app(MarkOrderPaid::class)($order, 'p24-1');

        $this->assertSame([RedemptionStatus::Used, RedemptionStatus::Used], VoucherRedemption::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame(OrderStatus::InProgress, $order->fresh()->status);
    }

    public function test_a_refused_payment_gives_the_money_back_to_the_voucher(): void
    {
        $voucher = $this->voucher(10000);
        $this->cartWithAVase();
        $this->post('/zamowienie/voucher', ['voucher_code' => $voucher->code]);

        $this->from('/zamowienie')->post('/zamowienie', $this->form(['blik_code' => '000000', 'expected_vouchers' => 10000]))
            ->assertRedirect('/zamowienie')
            ->assertSessionHasErrors('blik_code');

        $this->assertSame(10000, $voucher->fresh()->balance_minor);
        $this->assertSame(RedemptionStatus::Returned, VoucherRedemption::sole()->status);

        // The code stays for the next try, and pays again.
        $this->post('/zamowienie', $this->form(['expected_vouchers' => 10000]))->assertRedirect('/zamowienie/potwierdzenie');
        $this->assertSame(0, $voucher->fresh()->balance_minor);
    }

    public function test_two_orders_with_the_same_code_never_spend_more_than_its_balance(): void
    {
        config(['services.przelewy24' => ['merchant_id' => '11111', 'pos_id' => '', 'crc' => 'crc-key', 'api_key' => 'api-key', 'sandbox' => true]]);
        Http::fake(['https://sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response(['data' => ['token' => 'TOKEN-1'], 'responseCode' => 0])]);
        $voucher = $this->voucher(20000);

        // The first order holds all of it while its payment is open on Przelewy24's page.
        $this->cartWithAVase();
        $this->post('/zamowienie/voucher', ['voucher_code' => $voucher->code]);
        $this->post('/zamowienie', $this->form(['payment_method' => 'card', 'blik_code' => '', 'expected_vouchers' => 20000]));
        $first = Order::sole();
        $this->assertSame([20000, 0], [$first->vouchers_gross, $voucher->fresh()->balance_minor]);

        // A second browser with the same code meanwhile: nothing is left on it.
        $this->flushSession();
        $this->cartWithAVase();
        $this->post('/zamowienie/voucher', ['voucher_code' => $voucher->code])
            ->assertSessionHasErrors(['voucher_code' => 'Na tym voucherze nic już nie zostało. Jeśli to pomyłka, napisz do mnie.']);
        // The screen of a tab opened before the first order still promises the voucher: the order is not placed.
        $this->withSession(['checkout.vouchers' => [$voucher->code]])
            ->post('/zamowienie', $this->form(['expected_vouchers' => 20000]))
            ->assertSessionHas('checkout_notice', 'W koszyku coś się zmieniło — sprawdź sumę i zapłać jeszcze raz');
        $this->assertSame(1, Order::query()->count());

        // The first payment is never finished: the hold runs out, and the second browser spends the voucher.
        $this->travel(61)->minutes();
        $this->artisan('gifts:release-voucher-holds')->assertSuccessful();
        $this->assertSame(20000, $voucher->fresh()->balance_minor);
        config(['services.przelewy24.crc' => null]);
        $this->post('/zamowienie', $this->form(['expected_vouchers' => 20000]))->assertRedirect('/zamowienie/potwierdzenie');
        $this->assertSame(0, $voucher->fresh()->balance_minor);

        // Then the first payment comes in after all: the voucher has nothing for it, so Kasia hears about the gap.
        $first = app(MarkOrderPaid::class)($first, 'p24-late');

        $this->assertSame(0, $voucher->fresh()->balance_minor);
        $this->assertSame(20000, (int) VoucherRedemption::query()->where('status', RedemptionStatus::Used)->sum('amount_minor'));
        $this->assertSame(OrderStatus::Problem, $first->status);
        $this->assertStringContainsString('voucher '.$voucher->code.' (200 zł) został w międzyczasie wydany', $first->problem_note);
    }

    public function test_a_code_that_pays_nothing_says_why(): void
    {
        $this->cartWithAVase();
        $workshop = Voucher::factory()->create(['workshop_code' => 'wheel', 'seats' => 1]);
        $expired = $this->voucher(10000, ['valid_until' => '2026-11-19']);
        $spent = $this->voucher(10000, ['balance_minor' => 0]);

        $refusals = [
            '' => 'Wpisz kod z vouchera — wygląda tak: MA-7KQ2-9XHT.',
            'MA-0000-0000' => 'Nie znajduję takiego kodu. Przepisz go z vouchera — wygląda tak: MA-7KQ2-9XHT.',
            $workshop->code => 'To voucher na warsztat — wpiszesz go przy zapisie na termin, nie w sklepie.',
            $expired->code => 'Ten voucher był ważny do 19 listopada 2026. Napisz do mnie — coś wymyślimy.',
            $spent->code => 'Na tym voucherze nic już nie zostało. Jeśli to pomyłka, napisz do mnie.',
        ];

        foreach ($refusals as $code => $sentence) {
            $this->post('/zamowienie/voucher', ['voucher_code' => $code])->assertSessionHasErrors(['voucher_code' => $sentence]);
        }

        $this->assertSame([], session('checkout.vouchers', []));
    }

    public function test_kasia_sees_the_balance_and_history_and_can_give_an_orders_part_back(): void
    {
        $voucher = $this->voucher(30000);
        $this->cartWithAVase();
        $this->post('/zamowienie/voucher', ['voucher_code' => $voucher->code]);
        $this->post('/zamowienie', $this->form(['blik_code' => '', 'expected_vouchers' => 25500]));
        $order = Order::sole();
        $kasia = User::factory()->create();

        $this->actingAs($kasia)->get('/panel/prezenty?kod='.$voucher->code)
            ->assertOk()
            ->assertSee('Saldo 45 zł')
            ->assertSee($order->number)
            ->assertSee('wykorzystane');

        $this->get('/panel/zamowienia/'.$order->number)
            ->assertSee('Zapłacone voucherem')
            ->assertSee('Oddaj 255 zł na voucher');

        $this->put('/panel/prezenty/zamowienie/'.$order->id.'/oddaj-na-voucher')
            ->assertSessionHas('panel_status', '255 zł wróciło na vouchery. Klientka zapłaci nimi jeszcze raz tym samym kodem.');

        $this->assertSame(30000, $voucher->fresh()->balance_minor);
        $this->assertSame(RedemptionStatus::Returned, VoucherRedemption::sole()->status);
    }

    private function voucher(int $amount, array $attributes = []): Voucher
    {
        return Voucher::factory()->create(['amount_minor' => $amount, 'balance_minor' => $amount, 'valid_until' => '2027-11-20', ...$attributes]);
    }

    private function cartWithAVase(): ProductVariant
    {
        $product = Product::factory()->create(['name' => 'Wazony', 'slug' => 'wazony-'.uniqid(), 'category_id' => Category::factory()->create()->id, 'stamp_enabled' => false]);
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
