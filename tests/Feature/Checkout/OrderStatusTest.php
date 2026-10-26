<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\ParcelOnItsWay;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
            ['code' => 'courier', 'label' => 'Kurier InPost', 'price_gross' => 2200],
            ['code' => 'studio_pickup', 'label' => 'Odbiór w pracowni', 'price_gross' => 0],
        ]]);
    }

    public function test_a_parcel_marked_as_sent_brings_the_customer_a_mail_with_the_tracking_link(): void
    {
        Mail::fake();
        $order = $this->order();
        $this->actingAs(User::factory()->create());

        $this->get('/panel/zamowienia/'.$order->number)
            ->assertOk()
            ->assertSeeInOrder(['Status', 'W realizacji', 'Numer przesyłki', 'Wysłane — powiadom klientkę', 'Zakończ zamówienie', 'Oznacz problem']);

        $this->patch('/panel/zamowienia/'.$order->number.'/status', ['status' => 'shipped', 'tracking_number' => ' 6200 1234 5678 9012 '])
            ->assertRedirect('/panel/zamowienia/'.$order->number)
            ->assertSessionHas('panel_status', 'Wysłane — klientka dostanie maila z numerem przesyłki.');

        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertSame('6200123456789012', $order->tracking_number);
        $this->assertNotNull($order->shipped_at);

        Mail::assertQueued(ParcelOnItsWay::class, fn (ParcelOnItsWay $mail) => $mail->hasTo('ania@example.com'));

        $mail = new ParcelOnItsWay($order->load('items'));
        $mail->assertHasSubject('Zamówienie '.$order->number.' jest w drodze');
        $mail->assertSeeInOrderInHtml(['Paczka w drodze', $order->number, 'InPost napisze do Ciebie SMS-a i maila', 'https://inpost.pl/sledzenie-przesylek?number=6200123456789012', 'Numer przesyłki: 6200123456789012', 'W paczce', 'Wazony, Niski 16 cm × 2', 'InPost Paczkomat']);
        $mail->assertSeeInText('Śledź paczkę na stronie InPost: https://inpost.pl/sledzenie-przesylek?number=6200123456789012');

        $this->get('/panel/zamowienia')
            ->assertSeeInOrder(['0', 'Do wysłania'])
            ->assertSee('Wysłane (1)')
            ->assertSeeInOrder([$order->number, 'Wysłane']);

        $this->get('/panel/zamowienia/'.$order->number)
            ->assertSeeInOrder(['Wysłane', 'Nr przesyłki', '6200123456789012', 'Doręczone — zakończ', 'Wróć do „W realizacji”'])
            ->assertDontSee('Wysłane — powiadom klientkę');
    }

    public function test_an_order_can_end_have_a_problem_and_go_back_to_the_studio(): void
    {
        Mail::fake();
        $order = $this->order();
        $this->actingAs(User::factory()->create());

        $this->patch('/panel/zamowienia/'.$order->number.'/status', ['status' => 'completed'])
            ->assertSessionHas('panel_status', 'Zakończone — '.$order->number.' nie czeka już na Ciebie.');
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completed_at);

        $this->patch('/panel/zamowienia/'.$order->number.'/status', ['status' => 'problem', 'problem_note' => '  Paczka wróciła do nadawcy  ']);
        $order->refresh();
        $this->assertSame([OrderStatus::Problem, 'Paczka wróciła do nadawcy', null], [$order->status, $order->problem_note, $order->completed_at]);

        $this->get('/panel/zamowienia?status=problem')
            ->assertSee('Problem (1)')
            ->assertSeeInOrder([$order->number, 'Problem']);
        $this->get('/panel/zamowienia/'.$order->number)->assertSeeInOrder(['Problem', 'Paczka wróciła do nadawcy', 'Wysłane — powiadom klientkę']);

        $this->patch('/panel/zamowienia/'.$order->number.'/status', ['status' => 'in_progress'])
            ->assertSessionHas('panel_status', 'Z powrotem w realizacji.');
        $order->refresh();
        $this->assertSame([OrderStatus::InProgress, null, null], [$order->status, $order->problem_note, $order->shipped_at]);

        Mail::assertNothingQueued();
    }

    public function test_a_pickup_at_the_studio_is_never_sent_and_an_unpaid_order_waits_for_its_payment(): void
    {
        $pickup = $this->order(['shipping_method' => 'studio_pickup', 'shipping_gross' => 0]);
        $this->actingAs(User::factory()->create());

        $this->get('/panel/zamowienia/'.$pickup->number)
            ->assertSee('Odebrane — zakończ')
            ->assertDontSee('Wysłane — powiadom klientkę');
        $this->from('/panel/zamowienia/'.$pickup->number)
            ->patch('/panel/zamowienia/'.$pickup->number.'/status', ['status' => 'shipped'])
            ->assertSessionHasErrors(['status' => 'Tego statusu nie da się ustawić przy tym zamówieniu']);

        $parcel = $this->order();
        $this->from('/panel/zamowienia/'.$parcel->number)
            ->patch('/panel/zamowienia/'.$parcel->number.'/status', ['status' => 'shipped', 'tracking_number' => 'abc'])
            ->assertSessionHasErrors(['tracking_number' => 'Numer przesyłki to litery i cyfry z etykiety InPost — sprawdź, czy się zgadza']);
        $this->assertSame(OrderStatus::InProgress, $parcel->fresh()->status);

        $unpaid = $this->order(['status' => OrderStatus::New, 'payment_status' => PaymentStatus::Pending, 'paid_at' => null]);
        $this->get('/panel/zamowienia/'.$unpaid->number)->assertSee('Status zmienisz, gdy zamówienie będzie opłacone.');
        $this->patch('/panel/zamowienia/'.$unpaid->number.'/status', ['status' => 'completed'])->assertForbidden();
    }

    public function test_vouchers_sent_as_pdfs_are_done_the_moment_they_are_paid(): void
    {
        Mail::fake();
        $vouchers = $this->order(['shipping_method' => 'email', 'shipping_gross' => 0, 'status' => OrderStatus::New, 'payment_status' => PaymentStatus::Pending, 'paid_at' => null]);
        $parcel = $this->order(['status' => OrderStatus::New, 'payment_status' => PaymentStatus::Pending, 'paid_at' => null]);

        app(MarkOrderPaid::class)($vouchers, 'test-vouchers');
        app(MarkOrderPaid::class)($parcel, 'test-parcel');

        $this->assertSame(OrderStatus::Completed, $vouchers->fresh()->status);
        $this->assertNotNull($vouchers->fresh()->completed_at);
        $this->assertSame(OrderStatus::InProgress, $parcel->fresh()->status);
        $this->assertNull($parcel->fresh()->completed_at);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = []): Order
    {
        $order = Order::create([
            'status' => OrderStatus::InProgress,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker',
            'shipping_gross' => 1600,
            'total_gross' => 49400,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
            ...$attributes,
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);
        $order->items()->create(['product_name' => 'Wazony', 'variant_label' => 'Niski 16 cm', 'quantity' => 2, 'unit_price_gross' => 23900]);

        return $order;
    }
}
