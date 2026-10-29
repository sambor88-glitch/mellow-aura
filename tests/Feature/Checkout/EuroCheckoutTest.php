<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\IssueCertificates;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Mail\ParcelOnItsWay;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\CertificatePdf;
use App\Modules\Checkout\Support\VoucherCodes;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The English checkout: the basket in euro, paid in euro, and an order that remembers both its currency and its
 * language. The Polish checkout stays as it was.
 */
class EuroCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        Setting::create(['key' => 'free_shipping_threshold', 'value' => 30000]);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'note' => '1–2 dni robocze', 'price_gross' => 1600, 'price_eur' => 450],
            ['code' => 'courier', 'label' => 'Kurier InPost', 'price_gross' => 2200],
        ]]);
    }

    public function test_the_english_checkout_counts_in_euro_and_offers_what_takes_euro(): void
    {
        $this->basket();

        $this->get('/en/checkout')
            ->assertOk()
            ->assertSee('<title>Checkout | MellowAura</title>', false)
            ->assertSee('Three fields <em class="text-brown italic">and done</em>', false)
            ->assertSee('€39.00')
            // Delivery in euro, under its English name; a method without a euro price is not offered.
            ->assertSee('InPost parcel locker')
            ->assertSee('€4.50')
            ->assertDontSee('Kurier InPost')
            ->assertDontSee('InPost courier')
            ->assertSee('Pay €43.50')
            // Card and Przelewy24 take euro; BLIK and a transfer to the złoty account do not.
            ->assertSee('value="card"', false)
            ->assertSee('value="online_transfer"', false)
            ->assertDontSee('value="blik"', false)
            ->assertDontSee('value="bank_transfer"', false)
            ->assertSee('I accept the <a href="'.url('/en/terms').'"', false)
            ->assertDontSee(',00 zł');

        $this->get('/zamowienie')
            ->assertSee('Jeszcze trzy pola')
            ->assertDontSee('€');
    }

    public function test_vouchers_pay_in_złoty_only_so_the_english_checkout_never_takes_them(): void
    {
        // MA-211: a code typed in on the Polish checkout stays in the session, but euro never takes it.
        $voucher = Voucher::factory()->create(['amount_minor' => 15000, 'balance_minor' => 15000]);
        $this->basket();
        $this->withSession([VoucherCodes::SESSION_KEY => [$voucher->code]]);

        $this->get('/en/checkout')->assertOk()->assertDontSee('voucher_code')->assertDontSee($voucher->code)->assertSee('Pay €43.50');
        $this->post('/en/checkout', $this->form(['expected_vouchers' => 0]))->assertRedirect('/en/checkout/confirmation');

        $this->assertSame([4350, 0], [Order::sole()->total_gross, Order::sole()->vouchers_gross]);
        $this->assertSame(15000, $voucher->fresh()->balance_minor);
        $this->post('/en/checkout/voucher')->assertNotFound();
    }

    public function test_free_delivery_stays_in_złoty(): void
    {
        // 8 × €39 is far above the złoty threshold in any currency; euro has none.
        $this->basket(quantity: 8);

        $this->get('/en/checkout')->assertSee('Pay €316.50');
    }

    public function test_an_english_order_is_paid_in_euro_and_remembers_its_language(): void
    {
        $this->basket();

        $this->post('/en/checkout', $this->form())->assertRedirect('/en/checkout/confirmation');

        $order = Order::sole();
        $this->assertSame(['EUR', 'en', 4350, 450], [$order->currency, $order->locale, $order->total_gross, $order->shipping_gross]);
        $this->assertSame([[3900, 'EUR']], $order->items->map(fn ($item) => [$item->unit_price_gross, $item->currency])->all());
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);

        $this->get('/en/checkout/confirmation')
            ->assertOk()
            ->assertSee('Thank you. I’m packing.')
            ->assertSee('Order <strong class="font-medium select-all">'.$order->number.'</strong> is paid.', false)
            ->assertSee('€43.50')
            ->assertSee('InPost parcel locker');

        // The e-mail is still in Polish, but every amount in it is in euro (written the way of the language it renders in).
        Mail::assertQueued(OrderConfirmed::class, fn (OrderConfirmed $mail) => preg_match('/€43\.50|43,50 €/u', $mail->render()) && ! preg_match('/\d,\d\d zł/u', $mail->render()));
    }

    public function test_the_customer_hears_from_kasia_in_english_and_kasia_in_polish(): void
    {
        Setting::create(['key' => 'owner_email', 'value' => 'kasia@example.com']);
        $this->basket();

        $this->post('/en/checkout', $this->form());
        $order = Order::sole();

        Mail::assertQueued(OrderConfirmed::class, function (OrderConfirmed $mail) use ($order) {
            $html = $mail->render();

            return $mail->locale === 'en'
                && $mail->envelope()->subject === 'Order '.$order->number.' is paid'
                && str_contains($html, '<html lang="en">')
                && str_contains($html, 'Thank you. I’m packing.')
                && str_contains($html, 'Parcel locker KRA01M')
                && str_contains($html, 'InPost parcel locker')
                && str_contains($html, '<strong>Total</strong>')
                && str_contains($html, '€43.50')
                && ! str_contains($html, 'Dziękuję');
        });

        // The shipping notice later on, from the panel, speaks the order's language too.
        $order->update(['tracking_number' => '6000123']);
        $notice = new ParcelOnItsWay($order->fresh());
        $this->assertSame('Order '.$order->number.' is on its way', $notice->envelope()->subject);
        $this->assertStringContainsString('Your parcel is on its way', $notice->render());
    }

    public function test_an_english_order_gets_english_certificates(): void
    {
        $variant = $this->basket();
        $variant->product->update(['care_note' => 'Myć ręcznie', 'dimensions' => ['height_cm' => '9']]);
        $variant->product->translation('en')->update(['care_note' => 'Wash by hand']);
        Setting::create(['key' => 'care_rule_ceramics', 'value' => 'Zmywarka tak']);
        $this->post('/en/checkout', $this->form());
        $order = Order::sole();
        // Kasia prints it from the Polish panel.
        app()->setLocale('pl');

        $html = app(CertificatePdf::class)->html(app(IssueCertificates::class)($order), $order->locale);

        foreach (['<html lang="en">', 'certificate of uniqueness', 'Painted mug', 'Wash by hand', 'Height 9 cm', 'Fired on', 'Care'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['certyfikat unikatu', 'Myć ręcznie', 'Zmywarka tak', 'Pielęgnacja'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
        $this->assertSame('certificates-'.$order->number.'.pdf', CertificatePdf::filename($order->number, 'en'));
        // The panel itself stays in Polish.
        $this->assertSame('pl', app()->getLocale());
    }

    public function test_blik_never_takes_euro(): void
    {
        $this->basket();

        $this->from('/en/checkout')->post('/en/checkout', $this->form(['payment_method' => 'blik', 'blik_code' => '123456']))
            ->assertRedirect('/en/checkout')
            ->assertSessionHasErrors(['payment_method' => 'Choose how you’d like to pay']);

        $this->assertSame(0, Order::count());
    }

    public function test_english_mistakes_come_back_in_english(): void
    {
        $this->basket();

        $this->from('/en/checkout')->post('/en/checkout', $this->form(['email' => '', 'accept_terms' => '']))
            ->assertSessionHasErrors([
                'email' => 'Enter your e-mail — your confirmation goes there',
                'accept_terms' => 'Tick the box to accept the terms — I can’t take the order without it',
            ]);
    }

    public function test_the_panel_sets_the_euro_price_of_each_delivery(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/panel/ustawienia/dostawa', [
                'free_shipping_threshold' => '300',
                'shipping' => ['parcel_locker' => '16', 'courier' => '22'],
                'shipping_eur' => ['parcel_locker' => '5', 'courier' => ''],
            ])
            ->assertSessionHasNoErrors();

        $methods = collect(Setting::query()->where('key', 'shipping_methods')->sole()->value)->keyBy('code');
        $this->assertSame([500, null], [$methods['parcel_locker']['price_eur'], $methods['courier']['price_eur']]);

        $this->get('/panel/ustawienia')->assertSee('InPost Paczkomat — w angielskiej kasie');
    }

    public function test_delivery_gets_a_working_euro_price_that_never_overwrites_the_panel(): void
    {
        Setting::query()->where('key', 'shipping_methods')->update(['value' => json_encode([
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
            ['code' => 'courier', 'label' => 'Kurier InPost', 'price_gross' => 2200, 'price_eur' => 900],
            ['code' => 'studio_pickup', 'label' => 'Odbiór w pracowni', 'price_gross' => 0],
        ])]);

        (require base_path('app/Modules/Settings/Database/Migrations/2026_09_23_180000_add_working_euro_delivery_prices.php'))->up();

        $methods = collect(Setting::query()->where('key', 'shipping_methods')->sole()->value)->keyBy('code');
        $this->assertSame([1200, 900, 0], [$methods['parcel_locker']['price_eur'], $methods['courier']['price_eur'], $methods['studio_pickup']['price_eur']]);
    }

    private function basket(int $quantity = 1): ProductVariant
    {
        $category = Category::factory()->create();
        $category->translations()->create(['locale' => 'en', 'name' => 'Ceramics', 'slug' => 'ceramics']);
        $category->update(['group' => CategoryGroup::Ceramics]);
        $product = Product::factory()->create(['name' => 'Malowany kubek', 'category_id' => $category->id, 'stamp_enabled' => false]);
        $product->translations()->create(['locale' => 'en', 'name' => 'Painted mug', 'slug' => 'painted-mug']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => '', 'price_gross' => 14900, 'stock' => 10]);
        $variant->prices()->create(['currency' => 'EUR', 'amount_minor' => 3900]);

        $this->postJson('/en/basket', ['variant_id' => $variant->id, 'quantity' => $quantity])->assertOk();

        return $variant;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Anna Smith',
            'email' => 'anna@example.com',
            'phone' => '600 100 200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
            'payment_method' => 'card',
            'accept_terms' => '1',
            'expected_total' => 4350,
            ...$overrides,
        ];
    }
}
