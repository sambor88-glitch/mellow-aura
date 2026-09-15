<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Setting::create(['key' => 'free_shipping_threshold', 'value' => 40000]);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
            ['code' => 'studio_pickup', 'label' => 'Odbiór w pracowni', 'price_gross' => 0],
        ]]);
    }

    public function test_paying_saves_the_order_with_copies_of_names_prices_and_the_mug_text(): void
    {
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3);
        $mug = $this->variant('Kubki z cytatem', 'Twój tekst', 7900, stock: null, product: ['stamp_enabled' => true]);
        $this->postJson('/koszyk', ['variant_id' => $vase->id]);
        $this->postJson('/koszyk', ['variant_id' => $mug->id, 'custom_text' => 'jeszcze nie teraz']);

        $this->post('/zamowienie', $this->form(['expected_total' => 33400]))
            ->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::with('items')->sole();
        $this->assertMatchesRegularExpression('/^MA-\d{4}-\d{4,}$/', $order->number);
        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(PaymentMethod::Blik, $order->payment_method);
        $this->assertSame(['Anna Nowak', 'ania@example.com', '600100200'], [$order->name, $order->email, $order->phone]);
        $this->assertSame(['parcel_locker', 1600, 33400], [$order->shipping_method, $order->shipping_gross, $order->total_gross]);
        $this->assertSame(
            [['Wazony', 'Niski 16 cm', 1, 23900, null], ['Kubki z cytatem', 'Twój tekst', 1, 7900, 'JESZCZE NIE TERAZ']],
            $order->items->map(fn ($item) => [$item->product_name, $item->variant_label, $item->quantity, $item->unit_price_gross, $item->custom_text])->all(),
        );

        $mug->update(['price_gross' => 9900]);
        $vase->product->update(['name' => 'Wazony rzeźbione']);
        $this->assertSame([23900, 7900], $order->items()->orderBy('id')->pluck('unit_price_gross')->all());
        $this->assertSame('Wazony', $order->items()->orderBy('id')->value('product_name'));

        // Stock comes off only when a real payment is confirmed (MA-52).
        $this->assertSame(3, $vase->fresh()->stock);

        $this->get('/zamowienie/potwierdzenie')
            ->assertOk()
            ->assertSee('Dziękuję. Pakuję.')
            ->assertSee($order->number)
            ->assertSee('334,00 zł');

        $this->get('/zamowienie')->assertSee('Nic tu jeszcze nie ma');
    }

    public function test_missing_details_come_back_with_hints_and_the_cart_stays(): void
    {
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);
        $incomplete = ['shipping_method' => 'parcel_locker', 'payment_method' => 'blik', 'blik_code' => '123', 'expected_total' => 25500];

        // Check the page first: asserting on the session empties the flashed errors for the next request.
        $this->from('/zamowienie')
            ->followingRedirects()
            ->post('/zamowienie', $incomplete)
            ->assertSee('Bez numeru telefonu kurier nie znajdzie paczkomatu')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('Wazony');

        $this->from('/zamowienie')
            ->post('/zamowienie', $incomplete)
            ->assertRedirect('/zamowienie')
            ->assertSessionHasErrors([
                'phone' => 'Bez numeru telefonu kurier nie znajdzie paczkomatu',
                'email' => 'Wpisz e-mail — wyślę na niego potwierdzenie',
                'name' => 'Wpisz imię i nazwisko — tak podpiszę paczkę',
                'blik_code' => 'Wpisz 6-cyfrowy kod z aplikacji banku',
            ]);

        $this->assertSame(0, Order::count());
    }

    public function test_an_address_has_to_be_whole_and_a_nip_has_to_add_up(): void
    {
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);

        $this->from('/zamowienie')
            ->post('/zamowienie', $this->form(['street' => 'Długa 1', 'invoice_nip' => '123-456-78-90']))
            ->assertSessionHasErrors(['postal_code', 'city', 'invoice_nip' => 'Ten NIP się nie zgadza — sprawdź cyfry']);

        $this->post('/zamowienie', $this->form(['street' => 'Długa 1', 'postal_code' => '30-001', 'city' => 'Kraków', 'invoice_nip' => '111-111-11-11']))
            ->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::where('payment_status', PaymentStatus::Paid)->sole();
        $this->assertEquals(['street' => 'Długa 1', 'postal_code' => '30-001', 'city' => 'Kraków'], $order->shipping_address);
        $this->assertSame('1111111111', $order->invoice_nip);
    }

    public function test_a_rejected_blik_code_keeps_the_cart_and_asks_to_try_again(): void
    {
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);

        $this->from('/zamowienie')
            ->followingRedirects()
            ->post('/zamowienie', $this->form(['blik_code' => '000000']))
            ->assertSee('Bank odrzucił kod. Spróbuj jeszcze raz albo zapłać przelewem.')
            ->assertSee('Wazony');

        $this->assertSame(PaymentStatus::Failed, Order::sole()->payment_status);
    }

    public function test_the_customer_pays_the_sum_the_screen_showed(): void
    {
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3);
        $this->postJson('/koszyk', ['variant_id' => $vase->id]);
        $vase->update(['price_gross' => 24900]);

        $this->from('/zamowienie')
            ->post('/zamowienie', $this->form(['expected_total' => 25500]))
            ->assertRedirect('/zamowienie')
            ->assertSessionHas('checkout_notice');

        $this->assertSame(0, Order::count());
    }

    public function test_delivery_is_free_above_the_threshold_and_card_needs_no_blik_code(): void
    {
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id, 'quantity' => 2]);

        $this->post('/zamowienie', $this->form(['payment_method' => 'card', 'blik_code' => '', 'expected_total' => 47800]))
            ->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::sole();
        $this->assertSame([0, 47800, PaymentMethod::Card], [$order->shipping_gross, $order->total_gross, $order->payment_method]);
    }

    public function test_the_confirmation_belongs_to_the_session_that_paid(): void
    {
        $this->get('/zamowienie/potwierdzenie')->assertRedirect(route('shop.index'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'phone' => '+48 600 100 200',
            'email' => 'ania@example.com',
            'name' => 'Anna Nowak',
            'shipping_method' => 'parcel_locker',
            'payment_method' => 'blik',
            'blik_code' => '123456',
            'expected_total' => 25500,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function variant(string $name, string $label, int $price, ?int $stock, array $product = []): ProductVariant
    {
        $owner = Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'category_id' => Category::factory()->create()->id,
            'is_published' => true,
            'stamp_enabled' => false,
            ...$product,
        ]);

        return ProductVariant::factory()->create(['product_id' => $owner->id, 'label' => $label, 'price_gross' => $price, 'stock' => $stock]);
    }
}
