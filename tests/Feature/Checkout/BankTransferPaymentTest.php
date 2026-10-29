<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Cart\Lines\ProductLine;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\PlaceOrder;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\NewOrderReceived;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** „Wpłata doszła” in the panel for an order paid by bank transfer (MA-216). */
class BankTransferPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);
        Setting::create(['key' => 'contact_email', 'value' => 'kasia@example.com']);
    }

    public function test_a_pending_bank_transfer_shows_the_button_with_the_amount_and_the_title_to_look_for(): void
    {
        $order = $this->order($this->variant(stock: 3));

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertOk()
            ->assertSee('Wpłata doszła')
            ->assertSee('Na pewno?')
            ->assertSee('255 zł z numerem '.$order->number, false)
            ->assertSee('action="'.route('admin.orders.paid', $order).'"', false)
            ->assertDontSee('Status zmienisz, gdy zamówienie będzie opłacone.');
    }

    public function test_marking_the_transfer_takes_the_piece_off_the_shelf_sends_the_mails_and_says_who_did_it(): void
    {
        $vase = $this->variant(stock: 3);
        $order = $this->order($vase);
        Mail::fake();

        $this->actingAs(User::factory()->create(['name' => 'Kasia']))
            ->patch('/panel/zamowienia/'.$order->number.'/wplata')
            ->assertRedirect('/panel/zamowienia/'.$order->number)
            ->assertSessionHas('panel_status', 'Opłacone — klientka dostanie maila z potwierdzeniem.');

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertSame('transfer-'.now()->format('Y-m-d'), $order->payment_provider_id);
        $this->assertSame('Kasia', $order->payment_confirmed_by);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(2, $vase->fresh()->stock);
        Mail::assertQueued(OrderConfirmed::class, fn (OrderConfirmed $mail) => $mail->hasTo('ania@example.com') && $mail->order->is($order));
        Mail::assertQueued(NewOrderReceived::class, fn (NewOrderReceived $mail) => $mail->hasTo('kasia@example.com'));

        $this->get('/panel/zamowienia/'.$order->number)
            ->assertOk()
            ->assertSeeInOrder(['Wpłata oznaczona przez', 'Kasia'])
            ->assertDontSee('Wpłata doszła');
    }

    public function test_marking_it_a_second_time_changes_nothing(): void
    {
        $vase = $this->variant(stock: 3);
        $order = $this->order($vase);
        $user = User::factory()->create(['name' => 'Kasia']);
        $this->actingAs($user)->patch('/panel/zamowienia/'.$order->number.'/wplata');
        $paidAt = $order->fresh()->paid_at;
        Mail::fake();

        $this->travel(5)->minutes();
        $this->actingAs(User::factory()->create(['name' => 'Ola']))
            ->patch('/panel/zamowienia/'.$order->number.'/wplata')
            ->assertRedirect('/panel/zamowienia/'.$order->number)
            ->assertSessionHas('panel_status', 'Ta wpłata jest już zapisana — nic się nie zmieniło.');

        $order->refresh();
        $this->assertSame('Kasia', $order->payment_confirmed_by);
        $this->assertEquals($paidAt, $order->paid_at);
        $this->assertSame(2, $vase->fresh()->stock);
        Mail::assertNothingQueued();
    }

    public function test_an_order_paid_another_way_cannot_be_marked(): void
    {
        $vase = $this->variant(stock: 3);
        $blik = $this->order($vase, 'blik');
        $online = $this->order($vase, 'online_transfer');
        Mail::fake();

        $this->actingAs(User::factory()->create());
        $this->patch('/panel/zamowienia/'.$blik->number.'/wplata')->assertForbidden();
        $this->patch('/panel/zamowienia/'.$online->number.'/wplata')->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $blik->fresh()->payment_status);
        $this->assertSame(PaymentStatus::Pending, $online->fresh()->payment_status);
        $this->assertSame(3, $vase->fresh()->stock);
        Mail::assertNothingQueued();
        $this->get('/panel/zamowienia/'.$blik->number)->assertOk()->assertDontSee('Wpłata doszła');
    }

    public function test_a_transfer_already_confirmed_elsewhere_keeps_its_payment(): void
    {
        $vase = $this->variant(stock: 3);
        $order = $this->order($vase);
        app(MarkOrderPaid::class)($order, 'p24-123');
        Mail::fake();

        $this->actingAs(User::factory()->create())
            ->patch('/panel/zamowienia/'.$order->number.'/wplata')
            ->assertRedirect('/panel/zamowienia/'.$order->number);

        $order->refresh();
        $this->assertSame('p24-123', $order->payment_provider_id);
        $this->assertNull($order->payment_confirmed_by);
        $this->assertSame(2, $vase->fresh()->stock);
        Mail::assertNothingQueued();
    }

    public function test_a_helper_sees_no_button_and_cannot_mark_the_transfer(): void
    {
        $vase = $this->variant(stock: 3);
        $order = $this->order($vase);
        Mail::fake();
        $this->actingAs(User::factory()->helper()->create());

        $this->get('/panel/zamowienia/'.$order->number)
            ->assertOk()
            ->assertDontSee('Wpłata doszła')
            ->assertDontSee(route('admin.orders.paid', $order))
            ->assertSee('Wpłatę zaznacza właścicielka');
        $this->patch('/panel/zamowienia/'.$order->number.'/wplata')->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertSame(3, $vase->fresh()->stock);
        Mail::assertNothingQueued();
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $order = $this->order($this->variant(stock: 3));

        $this->patch('/panel/zamowienia/'.$order->number.'/wplata')->assertRedirect('/panel/logowanie');

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    private function order(ProductVariant $variant, string $paymentMethod = 'bank_transfer'): Order
    {
        $line = new ProductLine('v'.$variant->id, 1, $variant->load('product'));

        return app(PlaceOrder::class)(collect([$line->key => $line]), [
            'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'phone' => '600100200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M', 'payment_method' => $paymentMethod,
        ], 1600);
    }

    private function variant(?int $stock): ProductVariant
    {
        $product = Product::factory()->create([
            'name' => 'Wazony',
            'slug' => 'wazony',
            'category_id' => Category::factory()->create()->id,
            'is_published' => true,
        ]);

        return ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => $stock]);
    }
}
