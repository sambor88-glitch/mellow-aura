<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order list opens on what waits to be sent, says how long each order has waited and marks the ones past
 * the dispatch time from the panel (MA-250).
 */
class AdminOrdersWaitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-29 10:00');
        Setting::query()->updateOrCreate(['key' => 'dispatch_days_max'], ['value' => 5]);
    }

    public function test_the_list_opens_on_what_waits_oldest_first_with_its_wait(): void
    {
        $late = $this->order('Anna Nowak', OrderStatus::InProgress, '2026-10-20 09:00');
        $fresh = $this->order('Ola Wiśniewska', OrderStatus::InProgress, '2026-10-29 08:00');
        $yesterday = $this->order('Iza Lis', OrderStatus::InProgress, '2026-10-28 08:00');
        $this->order('Ewa Kowalska', OrderStatus::Shipped, '2026-10-15 09:00');

        $response = $this->actingAs(User::factory()->create())->get('/panel/zamowienia')->assertOk();

        $response->assertSeeInOrder([
            'czeka 9 dni', $late->number, 'Anna Nowak', 'Termin wysyłki minął 27.10',
            'czeka 1 dzień', $yesterday->number, 'Iza Lis',
            'od dziś', $fresh->number, 'Ola Wiśniewska',
        ])
            ->assertDontSee('Ewa Kowalska')
            ->assertDontSee('>Opłacone</a>', false);
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>Do wysłania \(3\)</', $response->getContent());
        // Only the late one carries the red line.
        $this->assertSame(1, substr_count($response->getContent(), 'border-l-error'));

        $this->get('/panel/zamowienia?status=wszystkie')
            ->assertSee('Ewa Kowalska')
            ->assertSee('Anna Nowak')
            // A sent parcel has stopped waiting.
            ->assertDontSee('czeka 14 dni');
    }

    public function test_a_piece_stamped_to_order_is_never_late_by_the_shelfs_deadline(): void
    {
        $mug = $this->order('Anna Nowak', OrderStatus::InProgress, '2026-10-01 09:00');
        $mug->items()->create(['product_name' => 'Kubek z napisem', 'variant_label' => '350 ml', 'quantity' => 1, 'unit_price_gross' => 14900, 'custom_text' => 'DLA BABCI']);
        $shelf = $this->order('Ola Wiśniewska', OrderStatus::InProgress, '2026-10-01 09:00');
        $shelf->items()->create(['product_name' => 'Miska', 'variant_label' => '', 'quantity' => 1, 'unit_price_gross' => 11900]);

        $response = $this->actingAs(User::factory()->create())->get('/panel/zamowienia')
            ->assertSeeInOrder(['czeka 28 dni', $mug->number, 'Anna Nowak', 'Na zamówienie — bez terminu z półki'])
            ->assertSeeInOrder(['czeka 28 dni', $shelf->number, 'Ola Wiśniewska', 'Termin wysyłki minął 8.10']);
        $this->assertSame(1, substr_count($response->getContent(), 'border-l-error'));

        $this->get('/panel')->assertSee('najdłużej czeka 28 dni · po terminie: 1')->assertSee('do wysłania · na zamówienie · '.$mug->number);
    }

    public function test_a_paid_order_without_a_parcel_never_waits_to_be_sent(): void
    {
        // A workshop booking, vouchers as PDFs and a payment for a custom order all go by e-mail.
        $order = Order::create([
            'status' => OrderStatus::New, 'name' => 'Iza Lis', 'email' => 'iza@example.com', 'phone' => '600100200',
            'shipping_method' => ShippingMethods::EMAIL, 'shipping_gross' => 0, 'total_gross' => 22000,
            'payment_method' => PaymentMethod::Blik, 'payment_status' => PaymentStatus::Pending,
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);

        app(MarkOrderPaid::class)($order, 'test-email-only');

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->actingAs(User::factory()->create())->get('/panel/zamowienia')->assertDontSee('Iza Lis')->assertSee('Do wysłania (0)');
        $this->get('/panel')->assertSee('wszystko wysłane')->assertDontSee('Iza Lis');
    }

    public function test_without_a_dispatch_time_nothing_is_late(): void
    {
        Setting::query()->whereIn('key', ['dispatch_days_min', 'dispatch_days_max'])->delete();
        $this->order('Anna Nowak', OrderStatus::InProgress, '2026-10-01 09:00');

        $this->actingAs(User::factory()->create())->get('/panel/zamowienia')
            ->assertSee('czeka 28 dni')
            ->assertDontSee('Termin wysyłki minął')
            ->assertDontSee('border-l-error');
    }

    public function test_with_nothing_to_send_the_list_says_so(): void
    {
        $this->actingAs(User::factory()->create())->get('/panel/zamowienia')
            ->assertSee('Wszystko wysłane. Nowe opłacone zamówienie pojawi się tutaj razem z adresem do wysyłki.');
    }

    private function order(string $name, OrderStatus $status, string $paidAt): Order
    {
        $order = Order::create([
            'status' => $status, 'name' => $name, 'email' => 'ania@example.com', 'phone' => '600100200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M', 'shipping_gross' => 1600, 'total_gross' => 25500,
            'payment_method' => PaymentMethod::Blik, 'payment_status' => PaymentStatus::Paid, 'paid_at' => $paidAt,
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);

        return $order;
    }
}
