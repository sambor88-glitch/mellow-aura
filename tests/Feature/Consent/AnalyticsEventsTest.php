<?php

namespace Tests\Feature\Consent;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AnalyticsEventsTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $vase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Setting::create(['key' => 'google_analytics_id', 'value' => 'G-AB12CD34EF']);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);

        $vases = Category::factory()->create(['name' => 'Wazony i patery', 'group' => CategoryGroup::Ceramics]);
        $product = Product::factory()->create(['slug' => 'wazony', 'name' => 'Wazony', 'category_id' => $vases->id]);
        $this->vase = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => 5]);
    }

    public function test_a_product_page_carries_view_item_with_the_same_id_as_the_google_feed(): void
    {
        $events = $this->events($this->get('/produkt/wazony')->assertOk()->getContent());

        $this->assertSame([[
            'name' => 'view_item',
            'params' => [
                'currency' => 'PLN',
                'value' => 239,
                'items' => [[
                    'item_id' => 'v'.$this->vase->id,
                    'item_name' => 'Wazony',
                    'item_brand' => 'MellowAura',
                    'item_category' => 'Wazony i patery',
                    'item_variant' => 'Niski 16 cm',
                    'price' => 239,
                    'quantity' => 1,
                ]],
            ],
        ]], $events->all());
    }

    public function test_without_analytics_in_the_panel_the_page_carries_no_events(): void
    {
        Setting::query()->whereKey('google_analytics_id')->delete();

        $this->get('/produkt/wazony')->assertOk()->assertDontSee('data-analytics-event', false);
    }

    public function test_the_mug_page_carries_the_size_it_opens_with_and_never_the_customers_text(): void
    {
        $this->seed(SettingsSeeder::class);

        $item = $this->events($this->get('/kubek-z-napisem?rozmiar=duzy')->getContent())->first()['params']['items'][0];
        $this->assertSame(['mug-duzy', 'Kubek z napisem', 'Duży 400 ml', 95], [$item['item_id'], $item['item_name'], $item['item_variant'], $item['price']]);

        $analytics = $this->postJson('/koszyk', ['type' => 'mug', 'text' => 'Najlepsza Babcia', 'size' => 'Mały', 'glaze' => 'cobalt'])->assertOk()->json('analytics');

        $this->assertSame('add_to_cart', $analytics['name']);
        $this->assertSame('mug-maly', $analytics['params']['items'][0]['item_id']);
        $this->assertStringNotContainsStringIgnoringCase('babcia', json_encode($analytics, JSON_UNESCAPED_UNICODE));
    }

    public function test_adding_to_the_cart_answers_with_what_went_in(): void
    {
        $analytics = $this->postJson('/koszyk', ['variant_id' => $this->vase->id, 'quantity' => 2])->assertOk()->json('analytics');

        $this->assertSame('add_to_cart', $analytics['name']);
        $this->assertSame(478, $analytics['params']['value']);
        $this->assertSame(['v'.$this->vase->id, 2, 239], [$analytics['params']['items'][0]['item_id'], $analytics['params']['items'][0]['quantity'], $analytics['params']['items'][0]['price']]);

        // Only what is left on the shelf goes in and is counted; once the shelf is empty, nothing is.
        $this->assertSame(3, $this->postJson('/koszyk', ['variant_id' => $this->vase->id, 'quantity' => 9])->json('analytics.params.items.0.quantity'));
        $this->postJson('/koszyk', ['variant_id' => $this->vase->id])->assertOk()->assertJsonMissingPath('analytics');
    }

    public function test_a_voucher_keeps_the_names_and_the_dedication_out_of_analytics(): void
    {
        $vouchers = Category::factory()->create(['name' => 'Vouchery', 'group' => CategoryGroup::Workshops]);
        $product = Product::factory()->create(['name' => 'Voucher kwotowy', 'category_id' => $vouchers->id]);
        $amount = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => '150 zł', 'price_gross' => 15000, 'stock' => null]);

        $analytics = $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $amount->id, 'recipient_name' => 'Zosia Kowalska', 'dedication' => 'Sto lat, Zosiu!', 'sender_name' => 'Ania'])
            ->assertOk()
            ->json('analytics');

        $this->assertSame('v'.$amount->id, $analytics['params']['items'][0]['item_id']);
        $this->assertStringNotContainsString('Zosi', json_encode($analytics, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('Ania', json_encode($analytics, JSON_UNESCAPED_UNICODE));
    }

    public function test_the_checkout_carries_begin_checkout_and_the_confirmation_the_purchase_once_per_order(): void
    {
        $this->postJson('/koszyk', ['variant_id' => $this->vase->id, 'quantity' => 2]);

        $checkout = $this->events($this->get('/zamowienie')->assertOk()->getContent())->firstWhere('name', 'begin_checkout');
        $this->assertSame(478, $checkout['params']['value']);
        $this->assertSame('v'.$this->vase->id, $checkout['params']['items'][0]['item_id']);

        $this->post('/zamowienie', [
            'phone' => '600 100 200',
            'email' => 'ania@example.com',
            'name' => 'Anna Nowak',
            'shipping_method' => 'parcel_locker',
            'payment_method' => 'blik',
            'blik_code' => '123456',
            'accept_terms' => '1',
            'expected_total' => 49400,
        ])->assertRedirect('/zamowienie/potwierdzenie');

        $purchase = $this->events($this->get('/zamowienie/potwierdzenie')->assertOk()->getContent())->firstWhere('name', 'purchase');
        $number = $purchase['params']['transaction_id'];

        $this->assertStringStartsWith('MA-', $number);
        $this->assertSame('purchase-'.$number, $purchase['once']);
        $this->assertSame(478, $purchase['params']['value']);
        $this->assertSame(16, $purchase['params']['shipping']);
        $this->assertSame([['v'.$this->vase->id, 2]], array_map(fn (array $item) => [$item['item_id'], $item['quantity']], $purchase['params']['items']));
    }

    public function test_a_name_cannot_break_out_of_the_event(): void
    {
        $this->vase->product->update(['name' => 'Wazon</script><script>alert(1)</script>']);

        $this->get('/produkt/wazony')->assertOk()->assertDontSee('</script><script>alert(1)', false);
    }

    /**
     * The analytics events written into a page, decoded.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function events(string $html): Collection
    {
        preg_match_all('#<script type="application/json" data-analytics-event>(.*?)</script>#s', $html, $matches);

        return collect($matches[1])->map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR));
    }
}
