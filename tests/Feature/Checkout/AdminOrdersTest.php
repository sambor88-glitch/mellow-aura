<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrdersTest extends TestCase
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

    public function test_guests_cannot_see_orders(): void
    {
        $this->get('/panel/zamowienia')->assertRedirect('/panel/logowanie');
    }

    public function test_the_list_shows_paid_orders_flags_a_missing_piece_and_keeps_unpaid_ones_apart(): void
    {
        $paid = $this->order(['name' => 'Anna Nowak'], PaymentStatus::Paid, [['Wazony', 'Niski 16 cm', 2, 23900, null, 0]]);
        $short = $this->order(['name' => 'Ewa Kowalska'], PaymentStatus::Paid, [['Kubki z cytatem', 'Królowa matka', 1, 7900, null, 1]]);
        $this->order(['name' => 'Jan Zieliński'], PaymentStatus::Failed, [['Talerze', 'Deserowy', 1, 8900, null, 0]]);

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia')
            ->assertOk()
            ->assertSeeInOrder(['Dziś', '2', 'Do wysłania'])
            ->assertSeeInOrder([
                $short->number, 'Ewa Kowalska', 'Kubki z cytatem (Królowa matka) × 1', 'Problem: brak sztuki',
                $paid->number, 'Anna Nowak', 'Wazony (Niski 16 cm) × 2', '494,00 zł', 'W realizacji',
            ])
            ->assertSee('Nieopłacone (1)')
            ->assertDontSee('Jan Zieliński');

        $this->get('/panel/zamowienia?platnosc=nieoplacone')
            ->assertOk()
            ->assertSee('Jan Zieliński')
            ->assertSee('Nieudana płatność')
            ->assertDontSee('Anna Nowak');
    }

    public function test_the_details_show_contact_delivery_and_the_text_to_stamp(): void
    {
        $order = $this->order([
            'phone' => '600100200',
            'shipping_address' => ['street' => 'Długa 1', 'postal_code' => '30-001', 'city' => 'Kraków'],
            'note' => 'To prezent, dołóż kartkę',
            'invoice_nip' => '1111111111',
        ], PaymentStatus::Paid, [['Kubki z cytatem', 'Twój tekst', 1, 7900, 'KAWA <B>NAJPIERW</B>', 1]]);

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertOk()
            ->assertSee('Zamówienie '.$order->number)
            ->assertSee('href="mailto:ania@example.com"', false)
            ->assertSee('600 100 200')
            ->assertSee('InPost Paczkomat')
            ->assertSee('Długa 1, 30-001 Kraków')
            ->assertSee('To prezent, dołóż kartkę')
            ->assertSee('1111111111')
            ->assertSee('„KAWA &lt;B&gt;NAJPIERW&lt;/B&gt;”', false)
            ->assertSee('Brakuje 1 szt.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{string, string, int, int, ?string, int}>  $items  name, variant, quantity, price, text, missing
     */
    private function order(array $attributes, PaymentStatus $payment, array $items): Order
    {
        $order = Order::create([
            'status' => $payment === PaymentStatus::Paid ? OrderStatus::InProgress : OrderStatus::New,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker',
            'shipping_gross' => 1600,
            'total_gross' => collect($items)->sum(fn (array $item) => $item[2] * $item[3]) + 1600,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => $payment,
            'paid_at' => $payment === PaymentStatus::Paid ? now() : null,
            ...$attributes,
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);

        foreach ($items as [$name, $label, $quantity, $price, $text, $missing]) {
            $order->items()->create([
                'product_name' => $name,
                'variant_label' => $label,
                'quantity' => $quantity,
                'unit_price_gross' => $price,
                'custom_text' => $text,
                'missing_quantity' => $missing,
            ]);
        }

        return $order;
    }
}
