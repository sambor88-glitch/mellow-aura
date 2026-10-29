<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Content\Mail\ContactMessageReceived;
use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * „Na dziś” (MA-250): four tiles that lead to filtered lists and what waits for Kasia, the oldest first.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-29 10:00');
        Setting::query()->updateOrCreate(['key' => 'dispatch_days_max'], ['value' => 5]);
    }

    public function test_the_owner_sees_four_tiles_that_count_what_waits(): void
    {
        config(['queue.default' => 'database']);
        $this->order('Anna Nowak', OrderStatus::InProgress, '2026-10-20 09:00');
        $this->order('Ola Wiśniewska', OrderStatus::InProgress, '2026-10-28 09:00');
        $this->order('Ewa Kowalska', OrderStatus::Problem, '2026-10-27 09:00');
        $this->order('Iza Lis', OrderStatus::Shipped, '2026-10-26 09:00');
        $this->order('Jan Zieliński', OrderStatus::New, '2026-10-26 09:00', PaymentStatus::Pending);
        $this->idea(CustomOrderStatus::New);
        $this->idea(CustomOrderStatus::Quoting);
        $this->failedMail();

        $this->actingAs(User::factory()->create())
            ->get('/panel')
            ->assertOk()
            ->assertSeeInOrder(['Na dziś', 'Czwartek, 29 października.'])
            // The failed mails notice stays above the tiles.
            ->assertSeeInOrder(['1 mail nie wyszedł', '2', 'do wysłania', 'najdłużej czeka 9 dni — po terminie', '1', 'nowe pomysły', '1', 'problem', '1', 'niewysłane maile'])
            ->assertSee('href="'.route('admin.orders.index').'"', false)
            ->assertSee('href="'.route('admin.custom-orders.index', ['status' => 'new']).'"', false)
            ->assertSee('href="'.route('admin.orders.index', ['status' => 'problem']).'"', false)
            ->assertSee('href="'.route('admin.failed-mails.index').'"', false)
            // The old grid of every section is gone: the menu is the only menu.
            ->assertDontSee('Kto zapłacił, co wysłać i jaki napis wbić w glinę.');
    }

    public function test_what_waits_is_listed_oldest_first_and_a_late_parcel_says_so(): void
    {
        $this->order('Ola Wiśniewska', OrderStatus::InProgress, '2026-10-28 09:00');
        $this->order('Anna Nowak', OrderStatus::InProgress, '2026-10-20 09:00');
        $this->idea(CustomOrderStatus::New, ['name' => 'Kasia Lis', 'created_at' => '2026-10-25 12:00']);

        $this->actingAs(User::factory()->create())
            ->get('/panel')
            ->assertSeeInOrder([
                'Wymaga Twojej reakcji',
                '9 dni', 'do wysłania', 'Anna Nowak', 'Termin wysyłki minął 27.10', 'Spakuj i wyślij',
                '4 dni', 'zamówienie indywidualne', 'Kasia Lis', 'Wyceń',
                '1 dzień', 'do wysłania', 'Ola Wiśniewska',
            ])
            ->assertDontSee('Na dziś wszystko zrobione');
    }

    public function test_with_nothing_waiting_the_day_is_done(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/panel')
            ->assertSee('wszystko wysłane')
            ->assertSee('Na dziś wszystko zrobione');
    }

    public function test_a_helper_gets_only_the_order_tiles_and_orders(): void
    {
        $this->order('Anna Nowak', OrderStatus::InProgress, '2026-10-27 09:00');
        $this->idea(CustomOrderStatus::New, ['name' => 'Kasia Lis']);

        $response = $this->actingAs(User::factory()->helper()->create())->get('/panel')->assertOk();
        $tiles = (string) str($response->getContent())->betweenFirst('<main', 'Wymaga Twojej reakcji');

        $this->assertSame(2, substr_count($tiles, 'tracking-[0.14em] text-label uppercase'));
        $this->assertStringContainsString('do wysłania', $tiles);
        $this->assertStringContainsString('problem', $tiles);
        $response->assertSee('Anna Nowak')
            ->assertDontSee('nowe pomysły')
            ->assertDontSee('Kasia Lis')
            ->assertDontSee('niewysłane maile');
    }

    private function order(string $name, OrderStatus $status, string $paidAt, PaymentStatus $payment = PaymentStatus::Paid): Order
    {
        $order = Order::create([
            'status' => $status, 'name' => $name, 'email' => 'ania@example.com', 'phone' => '600100200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M', 'shipping_gross' => 1600, 'total_gross' => 25500,
            'payment_method' => PaymentMethod::Blik, 'payment_status' => $payment, 'paid_at' => $payment === PaymentStatus::Paid ? $paidAt : null,
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);
        $order->items()->create(['product_name' => 'Wazony', 'variant_label' => 'Niski 16 cm', 'quantity' => 1, 'unit_price_gross' => 23900]);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function idea(CustomOrderStatus $status, array $attributes = []): CustomOrderRequest
    {
        return CustomOrderRequest::query()->create([
            'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'kind' => BriefKind::Mugs,
            'description' => 'Kubki dla świadków', 'status' => $status, ...$attributes,
        ]);
    }

    /** A real queued mail moved to the failed jobs, as the worker leaves it after the last attempt. */
    private function failedMail(): void
    {
        Mail::to('kasia@example.com')->send(new ContactMessageReceived('Ola', 'ola@example.com', null, 'Dzień dobry'));
        $job = DB::table('jobs')->latest('id')->first();
        DB::table('jobs')->where('id', $job->id)->delete();
        app(FailedJobProviderInterface::class)->log('database', 'default', $job->payload, new RuntimeException('Serwer poczty nie odpowiada'));
    }
}
