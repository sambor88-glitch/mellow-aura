<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
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

    public function test_the_order_needs_the_terms_accepted_and_keeps_their_version_and_time(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(17, 40));
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3);
        $this->postJson('/koszyk', ['variant_id' => $vase->id]);

        $this->get('/zamowienie')
            ->assertSee('name="accept_terms"', false)
            ->assertSee('href="'.route('content.terms').'"', false);

        $this->from('/zamowienie')
            ->followingRedirects()
            ->post('/zamowienie', [...$this->form(['expected_total' => 25500]), 'accept_terms' => ''])
            ->assertSee('Zaznacz akceptację regulaminu — bez niej nie mogę przyjąć zamówienia');
        $this->assertSame(0, Order::count());

        $this->post('/zamowienie', $this->form(['expected_total' => 25500]))->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::sole();
        $this->assertSame('wersja 0.2 z 16 września 2026', $order->terms_version);
        $this->assertSame('2026-11-20 17:40', $order->terms_accepted_at->format('Y-m-d H:i'));

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertSee('Regulamin zaakceptowany: wersja 0.2 z 16 września 2026, 20.11.2026, 17:40');
    }

    public function test_paying_saves_the_order_with_copies_of_names_prices_and_the_mug_text(): void
    {
        Setting::create(['key' => 'dispatch_days_min', 'value' => 3]);
        Setting::create(['key' => 'dispatch_days_max', 'value' => 5]);
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
        $this->assertSame(['PLN', 'PLN'], $order->items()->orderBy('id')->pluck('currency')->all());
        $this->assertSame('Wazony', $order->items()->orderBy('id')->value('product_name'));

        // The confirmed payment took the vase off the shelf; the mug with the customer's text has no stock.
        $this->assertSame(2, $vase->fresh()->stock);
        $this->assertNull($mug->fresh()->stock);

        $this->get('/zamowienie/potwierdzenie')
            ->assertOk()
            ->assertSee('Dziękuję. Pakuję.')
            ->assertSee($order->number)
            ->assertSee('334,00 zł')
            ->assertSeeInOrder(['Wysyłka', '3–5 dni roboczych']);

        $this->get('/zamowienie')->assertSee('Nic tu jeszcze nie ma');
    }

    public function test_a_feature_nobody_expects_is_accepted_on_its_own_and_kept_with_the_item(): void
    {
        $plate = $this->variant('Talerz ze złotem', 'Duży', 23900, stock: 3, product: ['deviation' => 'Nie do zmywarki ani mikrofalówki — złota krawędź.']);
        $this->postJson('/koszyk', ['variant_id' => $plate->id]);
        $field = 'accept_deviations[v'.$plate->id.']';

        $this->get('/zamowienie')
            ->assertSeeInOrder(['Talerz ze złotem', 'name="'.$field.'"', 'Akceptuję: nie do zmywarki ani mikrofalówki — złota krawędź', 'Akceptuję <a'], false);

        $this->from('/zamowienie')
            ->followingRedirects()
            ->post('/zamowienie', $this->form())
            ->assertSee('Zaznacz, że akceptujesz tę cechę — bez tego nie mogę przyjąć zamówienia');
        $this->assertSame(0, Order::count());

        $this->post('/zamowienie', [...$this->form(), 'accept_deviations' => ['v'.$plate->id => '1']])
            ->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::with('items')->sole();
        $this->assertSame('Nie do zmywarki ani mikrofalówki — złota krawędź.', $order->items->sole()->accepted_deviation);

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertSeeInOrder(['Talerz ze złotem', 'Klientka zaakceptowała osobnym polem:', 'Nie do zmywarki ani mikrofalówki — złota krawędź.']);
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

    public function test_after_a_mistake_above_the_blik_field_asks_for_a_new_code(): void
    {
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);

        // The code was fine, but it is never flashed back, so it has to be typed again.
        $this->from('/zamowienie')
            ->followingRedirects()
            ->post('/zamowienie', $this->form(['email' => 'ania']))
            ->assertSeeInOrder(['Adres e-mail bez małpy', 'Popraw pola wyżej i wpisz nowy kod BLIK', 'Płacę'])
            ->assertDontSee('value="123456"', false);

        // A wrong code alone says what is wrong with the code.
        $this->from('/zamowienie')
            ->followingRedirects()
            ->post('/zamowienie', $this->form(['blik_code' => '12']))
            ->assertSee('Wpisz 6-cyfrowy kod z aplikacji banku')
            ->assertDontSee('Popraw pola wyżej');

        // Without a mistake the field only says how long the code lives.
        $this->get('/zamowienie')->assertSee('6 cyfr, ważne 2 minuty')->assertDontSee('Popraw pola wyżej');

        $this->assertSame(0, Order::count());
    }

    public function test_the_courier_needs_the_whole_address_and_a_nip_has_to_add_up(): void
    {
        $this->withCourier();
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);
        $courier = ['shipping_method' => 'courier', 'expected_total' => 25900];

        $this->from('/zamowienie')
            ->post('/zamowienie', $this->form($courier))
            ->assertSessionHasErrors([
                'street' => 'Wpisz ulicę i numer — bez nich kurier nie trafi',
                'postal_code' => 'Wpisz kod pocztowy, np. 30-001',
                'city' => 'Wpisz miasto',
            ]);

        $this->from('/zamowienie')
            ->post('/zamowienie', $this->form([...$courier, 'street' => 'Długa 1', 'invoice_nip' => '123-456-78-90']))
            ->assertSessionHasErrors(['postal_code', 'city', 'invoice_nip' => 'Ten NIP się nie zgadza — sprawdź cyfry']);

        $this->from('/zamowienie')
            ->post('/zamowienie', $this->form([...$courier, 'street' => 'Długa 1', 'postal_code' => '300-01', 'city' => 'Kraków']))
            ->assertSessionHasErrors(['postal_code' => 'Kod pocztowy wpisz jak na kopercie, np. 30-001']);
        $this->assertSame(0, Order::count());

        // A phone's number pad has no dash, so the code typed as digits alone is fine.
        $this->post('/zamowienie', $this->form([...$courier, 'street' => 'Długa 1', 'postal_code' => '30001', 'city' => 'Kraków', 'invoice_nip' => '111-111-11-11']))
            ->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::sole();
        $this->assertSame('courier', $order->shipping_method);
        $this->assertEquals(['street' => 'Długa 1', 'postal_code' => '30-001', 'city' => 'Kraków'], $order->shipping_address);
        $this->assertSame('1111111111', $order->invoice_nip);
    }

    public function test_the_parcel_locker_and_pickup_at_the_studio_need_no_address(): void
    {
        $this->withCourier();
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3);

        foreach ([['parcel_locker', 25500], ['studio_pickup', 23900]] as [$method, $total]) {
            $this->flushSession();
            $this->postJson('/koszyk', ['variant_id' => $vase->id]);
            // A half address typed for the courier before switching away is left out, not held against her.
            $this->post('/zamowienie', $this->form(['shipping_method' => $method, 'expected_total' => $total, 'street' => 'Długa 1']))
                ->assertSessionHasNoErrors()
                ->assertRedirect('/zamowienie/potwierdzenie');
        }

        $this->assertSame(
            [['parcel_locker', null], ['studio_pickup', null]],
            Order::orderBy('id')->get()->map(fn (Order $order) => [$order->shipping_method, $order->shipping_address])->all(),
        );
    }

    public function test_the_checkout_shows_the_address_with_the_courier_and_its_errors_in_place(): void
    {
        $this->withCourier();
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);

        $courierBlock = '/x-show="shipping === \'courier\'"\s*(x-cloak)?\s*class/';

        $page = $this->get('/zamowienie')
            ->assertSeeInOrder(['Faktura na firmę, dopisek do paczki', 'InPost Kurier', 'Adres dostawy dla kuriera', 'autocomplete="address-line1"', 'autocomplete="postal-code"', 'autocomplete="address-level2"'], false)
            ->getContent();
        $this->assertMatchesRegularExpression($courierBlock, $page);
        preg_match($courierBlock, $page, $hidden);
        $this->assertSame('x-cloak', $hidden[1] ?? null);

        $page = $this->from('/zamowienie')
            ->followingRedirects()
            ->post('/zamowienie', $this->form(['shipping_method' => 'courier', 'expected_total' => 25900]))
            ->assertSeeInOrder(['InPost Kurier', 'Adres dostawy dla kuriera', 'Wpisz ulicę i numer — bez nich kurier nie trafi'])
            ->getContent();
        preg_match($courierBlock, $page, $shown);
        $this->assertSame('', $shown[1] ?? '');
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

    public function test_the_parcel_locker_is_optional_and_saved_in_capitals_without_spaces(): void
    {
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);
        $this->from('/zamowienie')
            ->followingRedirects()
            ->post('/zamowienie', $this->form(['locker_code' => 'kra 01m?']))
            ->assertSee('Kod paczkomatu to litery i cyfry, np. KRA01M — sprawdź go albo zostaw puste');
        $this->assertSame(0, Order::count());

        $this->post('/zamowienie', $this->form(['locker_code' => ' kra 01m ']))->assertRedirect('/zamowienie/potwierdzenie');
        $this->assertSame('KRA01M', Order::sole()->locker_code);
        $this->get('/zamowienie/potwierdzenie')->assertSeeInOrder(['Paczkomat', 'KRA01M']);
    }

    public function test_an_empty_parcel_locker_leaves_the_choice_to_kasia(): void
    {
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);

        $this->post('/zamowienie', $this->form(['locker_code' => '']))->assertRedirect('/zamowienie/potwierdzenie');

        $this->assertNull(Order::sole()->locker_code);
        $this->get('/zamowienie/potwierdzenie')->assertSeeInOrder(['Paczkomat', 'Napiszę do Ciebie i ustalimy']);
    }

    public function test_a_locker_typed_before_switching_to_pickup_is_not_saved(): void
    {
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);

        $this->post('/zamowienie', $this->form(['shipping_method' => 'studio_pickup', 'locker_code' => 'not a code', 'expected_total' => 23900]))
            ->assertRedirect('/zamowienie/potwierdzenie');

        $this->assertNull(Order::sole()->locker_code);
    }

    public function test_a_pickup_in_the_studio_is_not_promised_a_parcel(): void
    {
        Setting::create(['key' => 'dispatch_days_min', 'value' => 3]);
        Setting::create(['key' => 'dispatch_days_max', 'value' => 5]);
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);

        $this->post('/zamowienie', $this->form(['shipping_method' => 'studio_pickup', 'expected_total' => 23900]))
            ->assertRedirect('/zamowienie/potwierdzenie');

        $this->get('/zamowienie/potwierdzenie')->assertOk()
            ->assertSee('Odbiór w pracowni')
            ->assertDontSee('Pakuję')
            ->assertDontSee('zdjęcie paczki')
            ->assertDontSee('dni roboczych');
    }

    public function test_the_confirmation_belongs_to_the_session_that_paid(): void
    {
        $this->get('/zamowienie/potwierdzenie')->assertRedirect(route('shop.index'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function withCourier(): void
    {
        Setting::where('key', 'shipping_methods')->sole()->update(['value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
            ['code' => 'courier', 'label' => 'InPost Kurier', 'price_gross' => 2000],
            ['code' => 'studio_pickup', 'label' => 'Odbiór w pracowni', 'price_gross' => 0],
        ]]);
    }

    private function form(array $overrides = []): array
    {
        return [
            'phone' => '+48 600 100 200',
            'email' => 'ania@example.com',
            'name' => 'Anna Nowak',
            'shipping_method' => 'parcel_locker',
            'payment_method' => 'blik',
            'blik_code' => '123456', 'accept_terms' => '1',
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
