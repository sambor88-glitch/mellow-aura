<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ExchangeRate;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every order keeps the tax facts of its moment (MA-129): where the goods go, the VAT rate on each item — zero
 * included, with its legal basis — and the euro rate, so the WSTO count can be rebuilt from the orders alone.
 */
class OrderTaxTest extends TestCase
{
    use RefreshDatabase;

    private const EXEMPTION = 'zwolnienie podmiotowe, art. 113 ust. 1 ustawy o VAT';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600, 'price_eur' => 450],
        ]]);
        Setting::create(['key' => 'vat_rate', 'value' => 0]);
        Setting::create(['key' => 'vat_exemption', 'value' => self::EXEMPTION]);
        ExchangeRate::create(['currency' => 'EUR', 'rate' => '4.2653', 'effective_on' => '2026-10-23']);
    }

    public function test_a_polish_order_records_poland_a_zero_rate_with_its_basis_and_the_euro_rate(): void
    {
        $this->basket('/koszyk');

        $this->post('/zamowienie', $this->form(['payment_method' => 'blik', 'expected_total' => 16500]))->assertRedirect();

        $order = Order::with('items')->sole();
        $this->assertSame('PL', $order->delivery_country);
        $this->assertSame('4.2653', $order->eur_rate);
        $this->assertSame('2026-10-23', $order->eur_rate_on->toDateString());
        $this->assertSame([['0.00', self::EXEMPTION]], $order->items->map(fn ($item) => [$item->vat_rate, $item->vat_exemption])->all());

        // Kasia sees it next to the order, for the invoice.
        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertSee('Dostawa do: PL · VAT: zw. — '.self::EXEMPTION)
            ->assertSee('kurs EUR (NBP): 4,2653 z 23.10.2026');
    }

    public function test_an_euro_order_records_the_same_facts(): void
    {
        $this->basket('/en/basket');

        $this->post('/en/checkout', $this->form(['payment_method' => 'card', 'expected_total' => 4350]))->assertRedirect();

        $order = Order::with('items')->sole();
        $this->assertSame(['EUR', 'PL', '4.2653'], [$order->currency, $order->delivery_country, $order->eur_rate]);
        $this->assertSame('0.00', $order->items->sole()->vat_rate);
    }

    public function test_a_later_rate_never_rewrites_a_placed_order(): void
    {
        $this->basket('/koszyk');
        $this->post('/zamowienie', $this->form(['payment_method' => 'blik', 'expected_total' => 16500]));

        ExchangeRate::for('EUR')->update(['rate' => '4.4000', 'effective_on' => '2026-10-30']);

        $this->assertSame('4.2653', Order::sole()->fresh()->eur_rate);
    }

    public function test_once_vat_is_charged_the_item_keeps_the_rate_and_no_exemption(): void
    {
        Setting::where('key', 'vat_rate')->sole()->update(['value' => 23]);
        $this->basket('/koszyk');

        $this->post('/zamowienie', $this->form(['payment_method' => 'blik', 'expected_total' => 16500]));

        $item = Order::sole()->items->sole();
        $this->assertSame(['23.00', null], [$item->vat_rate, $item->vat_exemption]);
    }

    public function test_an_order_goes_through_before_the_first_euro_rate_is_fetched(): void
    {
        ExchangeRate::query()->delete();
        $this->basket('/koszyk');

        $this->post('/zamowienie', $this->form(['payment_method' => 'blik', 'expected_total' => 16500]))->assertRedirect();

        $this->assertNull(Order::sole()->eur_rate);
    }

    private function basket(string $endpoint): void
    {
        $category = Category::factory()->create();
        $category->translations()->create(['locale' => 'en', 'name' => 'Ceramics', 'slug' => 'ceramics']);
        $category->update(['group' => CategoryGroup::Ceramics]);
        $product = Product::factory()->create(['name' => 'Malowany kubek', 'category_id' => $category->id, 'stamp_enabled' => false]);
        $product->translations()->create(['locale' => 'en', 'name' => 'Painted mug', 'slug' => 'painted-mug']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => '', 'price_gross' => 14900, 'stock' => 10]);
        $variant->prices()->create(['currency' => 'EUR', 'amount_minor' => 3900]);

        $this->postJson($endpoint, ['variant_id' => $variant->id])->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides): array
    {
        return [
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600 100 200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
            'blik_code' => '123456',
            'accept_terms' => '1',
            ...$overrides,
        ];
    }
}
