<?php

namespace Tests\Feature\Checkout;

use App\Modules\Cart\CartLine;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\PlaceOrder;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);
    }

    public function test_a_confirmed_payment_takes_the_pieces_off_the_shelf_only_once(): void
    {
        $vase = $this->variant(stock: 3);
        $this->postJson('/koszyk', ['variant_id' => $vase->id, 'quantity' => 2]);

        $this->post('/zamowienie', $this->form(['expected_total' => 49400]))->assertRedirect('/zamowienie/potwierdzenie');
        $this->assertSame(1, $vase->fresh()->stock);

        app(MarkOrderPaid::class)(Order::sole(), 'test-repeated-confirmation');
        $this->assertSame(1, $vase->fresh()->stock);
    }

    public function test_a_rejected_payment_leaves_the_shelf_alone(): void
    {
        $vase = $this->variant(stock: 3);
        $this->postJson('/koszyk', ['variant_id' => $vase->id]);

        $this->from('/zamowienie')->post('/zamowienie', $this->form(['blik_code' => '000000']));

        $this->assertSame(PaymentStatus::Failed, Order::sole()->payment_status);
        $this->assertSame(3, $vase->fresh()->stock);
    }

    public function test_when_two_people_pay_for_the_last_piece_the_second_order_is_paid_but_marked_short(): void
    {
        $mug = $this->variant(stock: 1);
        $lines = collect(['v'.$mug->id => new CartLine('v'.$mug->id, $mug->load('product'), 1, null)]);
        $data = ['name' => 'Anna Nowak', 'email' => 'ania@example.com', 'phone' => '600100200', 'shipping_method' => 'parcel_locker', 'payment_method' => 'blik'];

        // Both checkouts started while the piece was still on the shelf.
        $first = app(PlaceOrder::class)($lines, $data, 1600);
        $second = app(PlaceOrder::class)($lines, $data, 1600);

        app(MarkOrderPaid::class)($first, 'test-first');
        app(MarkOrderPaid::class)($second, 'test-second');

        $this->assertSame(0, $mug->fresh()->stock);
        $this->assertSame(PaymentStatus::Paid, $second->fresh()->payment_status);
        $this->assertFalse($first->hasShortage());
        $this->assertTrue($second->hasShortage());
        $this->assertSame(1, $second->items()->value('missing_quantity'));

        $this->withSession(['checkout.order' => $second->number])
            ->get('/zamowienie/potwierdzenie')
            ->assertOk()
            ->assertSee('Ktoś kupił ostatnią sztukę chwilę przed Tobą.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'phone' => '600 100 200',
            'email' => 'ania@example.com',
            'name' => 'Anna Nowak',
            'shipping_method' => 'parcel_locker',
            'payment_method' => 'blik',
            'blik_code' => '123456',
            'expected_total' => 25500,
            ...$overrides,
        ];
    }

    private function variant(?int $stock): ProductVariant
    {
        $product = Product::factory()->create([
            'name' => 'Wazony',
            'slug' => 'wazony-'.fake()->unique()->numberBetween(1, 9999),
            'category_id' => Category::factory()->create()->id,
            'is_published' => true,
            'stamp_enabled' => false,
        ]);

        return ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => $stock]);
    }
}
