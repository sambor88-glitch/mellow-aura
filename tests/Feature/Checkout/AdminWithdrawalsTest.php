<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Enums\WithdrawalScope;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\Withdrawal;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminWithdrawalsTest extends TestCase
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

    public function test_guests_cannot_see_withdrawals(): void
    {
        $withdrawal = $this->withdrawal(null, ['submitted_at' => now()]);

        $this->get('/panel/odstapienia')->assertRedirect('/panel/logowanie');
        $this->patch('/panel/odstapienia/'.$withdrawal->id)->assertRedirect('/panel/logowanie');
    }

    public function test_the_list_puts_open_statements_first_with_the_refund_deadline_and_flags_an_unknown_order(): void
    {
        $order = $this->order();
        $this->withdrawal($order, ['name' => 'Anna Nowak', 'submitted_at' => Carbon::parse('2026-11-18 09:00'), 'handled_at' => Carbon::parse('2026-11-19 10:00')]);
        $this->withdrawal(null, ['name' => 'Ewa Kowalska', 'order_number' => 'MA-2026-9999', 'scope' => WithdrawalScope::Part, 'items' => 'Kubek „Królowa matka”', 'submitted_at' => Carbon::parse('2026-11-20 18:32')]);

        $this->actingAs(User::factory()->create())
            ->get('/panel/odstapienia')
            ->assertOk()
            ->assertSee('>Odstąpienia</a>', false)
            ->assertSeeInOrder([
                '20.11.2026', '18:32', 'Ewa Kowalska', 'MA-2026-9999', 'nie ma takiego zamówienia z tym adresem', 'Część zamówienia', 'Kubek „Królowa matka”', 'Zwrot do 4.12', 'Oznacz jako załatwione',
                '18.11.2026', 'Anna Nowak', 'href="'.route('admin.orders.show', $order).'"', 'Załatwione 19.11', 'Przywróć do załatwienia',
            ], false);
    }

    public function test_kasia_marks_a_statement_as_settled_and_can_bring_it_back(): void
    {
        $withdrawal = $this->withdrawal($this->order(), ['submitted_at' => now()]);
        $this->actingAs(User::factory()->create());

        $this->patch('/panel/odstapienia/'.$withdrawal->id)
            ->assertRedirect('/panel/odstapienia')
            ->assertSessionHas('panel_status', 'Załatwione — '.$withdrawal->order_number.' przeniesione na koniec listy.');
        $this->assertNotNull($withdrawal->fresh()->handled_at);

        $this->patch('/panel/odstapienia/'.$withdrawal->id);
        $this->assertNull($withdrawal->fresh()->handled_at);
    }

    public function test_the_order_shows_the_withdrawal_until_it_is_settled(): void
    {
        $order = $this->order();
        $withdrawal = $this->withdrawal($order, ['submitted_at' => Carbon::parse('2026-11-20 18:32')]);
        $this->actingAs(User::factory()->create());

        $this->get('/panel/zamowienia')->assertSeeInOrder([$order->number, 'Odstąpienie od umowy']);
        $this->get('/panel/zamowienia/'.$order->number)
            ->assertSeeInOrder(['Odstąpienie od umowy — Całe zamówienie', 'Oświadczenie przyszło 20 listopada 2026, 18:32', 'Pieniądze trzeba zwrócić do 4 grudnia', 'Pozycje']);

        $withdrawal->update(['handled_at' => now()]);

        $this->get('/panel/zamowienia')->assertDontSee('Odstąpienie od umowy');
        $this->get('/panel/zamowienia/'.$order->number)->assertSee('Załatwione');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function withdrawal(?Order $order, array $attributes): Withdrawal
    {
        return Withdrawal::create([
            'order_id' => $order?->id,
            'order_number' => $order?->number ?? 'MA-2026-1047',
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'scope' => WithdrawalScope::Whole,
            ...$attributes,
        ]);
    }

    private function order(): Order
    {
        $order = Order::create([
            'status' => OrderStatus::InProgress,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker',
            'shipping_gross' => 1600,
            'total_gross' => 25500,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);
        $order->items()->create(['product_name' => 'Wazony', 'variant_label' => 'Niski 16 cm', 'quantity' => 1, 'unit_price_gross' => 23900]);

        return $order;
    }
}
