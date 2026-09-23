<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\IssueCertificates;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Mail\ParcelOnItsWay;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\CertificatePdf;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Payments\Gateways\StripeGateway;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Stripe\StripeClient;
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
        $this->assertSame([3900], $order->items->pluck('unit_price_gross')->all());
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
                && str_contains($html, 'I’ll find your parcel locker by your phone number, 600100200')
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

    public function test_stripe_is_asked_for_the_currency_of_the_order(): void
    {
        $asked = [];
        $stripe = new class(['api_key' => 'sk_test_x']) extends StripeClient
        {
            public ?object $intents = null;

            public function __get($name)
            {
                return $name === 'paymentIntents' ? $this->intents : parent::__get($name);
            }
        };
        $stripe->intents = new class($asked)
        {
            public function __construct(public array &$asked) {}

            public function create(array $params): object
            {
                $this->asked[] = $params;

                return (object) ['id' => 'pi_test', 'client_secret' => 'pi_test_secret'];
            }
        };
        $gateway = new StripeGateway($stripe, app(Alerts::class));

        $euro = $this->order(['currency' => 'EUR', 'total_gross' => 4350, 'payment_method' => PaymentMethod::Card]);
        $zloty = $this->order(['total_gross' => 25500, 'payment_method' => PaymentMethod::Blik]);

        $this->assertSame('pi_test_secret', $gateway->start($euro, null)->clientSecret);
        $gateway->start($zloty, '123456');

        $this->assertSame([['eur', 4350, ['card']], ['pln', 25500, ['blik']]], array_map(fn (array $params) => [$params['currency'], $params['amount'], $params['payment_method_types']], $asked));
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

    /** @param  array<string, mixed>  $attributes */
    private function order(array $attributes): Order
    {
        return Order::create([
            'number' => 'MA-2026-'.random_int(1000, 9999),
            'status' => 'new',
            'name' => 'Anna Smith',
            'email' => 'anna@example.com',
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker',
            'shipping_gross' => 0,
            'payment_status' => PaymentStatus::Pending,
            ...$attributes,
        ])->fresh();
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
            'shipping_method' => 'parcel_locker',
            'payment_method' => 'card',
            'accept_terms' => '1',
            'expected_total' => 4350,
            ...$overrides,
        ];
    }
}
