<?php

namespace Tests\Feature\Shipping;

use App\Models\User;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\ParcelOnItsWay;
use App\Modules\Checkout\Models\Order;
use App\Modules\Monitoring\Mail\Alert;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ShipX statuses moving the orders along (MA-133), against a faked ShipX: nothing here talks to InPost.
 */
class ShipmentSyncTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://sandbox-api-shipx-pl.easypack24.net/v1/';

    private const TRACKING = '520000011223344556677';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        config([
            'services.inpost.shipx_token' => 'shipx-token', 'services.inpost.shipx_organization_id' => '1234', 'services.inpost.shipx_sandbox' => true,
            'monitoring.alert_email' => 'alerty@example.com',
        ]);
    }

    public function test_a_parcel_on_its_way_sends_the_order_and_the_customer_hears_once(): void
    {
        $order = $this->order();
        $this->shipment($order, 'confirmed');
        $this->shipx(['collected_from_sender', 'collected_from_sender', 'adopted_at_source_branch', 'ready_to_pickup']);

        foreach (range(1, 4) as $run) {
            $this->artisan('shipping:sync')->assertSuccessful();
        }

        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertSame(self::TRACKING, $order->tracking_number);
        $this->assertNotNull($order->shipped_at);
        $this->assertSame('ready_to_pickup', Shipment::sole()->status);
        Mail::assertQueued(ParcelOnItsWay::class, 1);
    }

    public function test_each_stage_of_the_journey_moves_the_order(): void
    {
        $cases = [
            ['dispatched_by_sender', OrderStatus::InProgress, OrderStatus::Shipped],
            ['out_for_delivery', OrderStatus::Problem, OrderStatus::Shipped],
            ['delivered', OrderStatus::Shipped, OrderStatus::Completed],
            ['delivered', OrderStatus::InProgress, OrderStatus::Completed],
            ['returned_to_sender', OrderStatus::Shipped, OrderStatus::Problem],
            ['canceled', OrderStatus::InProgress, OrderStatus::Problem],
            ['rejected_by_receiver', OrderStatus::Shipped, OrderStatus::Problem],
            ['confirmed', OrderStatus::InProgress, OrderStatus::InProgress],
            ['a_status_nobody_knows', OrderStatus::InProgress, OrderStatus::InProgress],
        ];

        foreach ($cases as $i => [$shipx, $from, $to]) {
            $order = $this->order(['status' => $from, 'shipped_at' => $from === OrderStatus::Shipped ? now() : null]);
            $this->shipment($order, 'created', 1000 + $i);
            Http::fake([self::API.'shipments/'.(1000 + $i) => Http::response(['id' => 1000 + $i, 'status' => $shipx, 'tracking_number' => self::TRACKING])]);

            $this->artisan('shipping:sync')->assertSuccessful();

            $this->assertSame($to, $order->fresh()->status, $shipx.' from '.$from->value);
        }
    }

    public function test_a_returned_parcel_leaves_kasia_a_note_she_can_act_on(): void
    {
        $order = $this->order(['status' => OrderStatus::Shipped, 'shipped_at' => now(), 'tracking_number' => self::TRACKING]);
        $this->shipment($order, 'ready_to_pickup');
        $this->shipx(['returned_to_sender']);

        $this->artisan('shipping:sync')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::Problem, $order->status);
        $this->assertSame('InPost: Wraca do Ciebie. Przesyłka '.self::TRACKING.'. Sprawdź ją w InPost Managerze i napisz do klientki.', $order->problem_note);

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertSee('Sprawdź ją w InPost Managerze')
            ->assertSee('Wraca do Ciebie');
    }

    public function test_an_order_kasia_closed_herself_stays_closed(): void
    {
        $order = $this->order(['status' => OrderStatus::Completed, 'completed_at' => now()->subDay()]);
        $this->shipment($order, 'confirmed');
        $this->shipx(['out_for_delivery']);

        $this->artisan('shipping:sync')->assertSuccessful();

        // A closed order is not even asked about.
        Http::assertNothingSent();

        // Nor does the label button bring it back, whatever ShipX says.
        $this->actingAs(User::factory()->create())->get('/panel/zamowienia/'.$order->number.'/etykieta');
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        Mail::assertNotQueued(ParcelOnItsWay::class);
    }

    public function test_an_order_kasia_already_sent_is_not_sent_again(): void
    {
        $order = $this->order(['status' => OrderStatus::Shipped, 'shipped_at' => $sent = now()->subHour()->startOfSecond(), 'tracking_number' => self::TRACKING]);
        $this->shipment($order, 'confirmed');
        $this->shipx(['taken_by_courier']);

        $this->artisan('shipping:sync')->assertSuccessful();

        $this->assertEquals($sent, $order->fresh()->shipped_at);
        Mail::assertNotQueued(ParcelOnItsWay::class);
    }

    public function test_an_order_kasia_sent_and_took_back_by_mistake_brings_no_second_mail(): void
    {
        $order = $this->order();
        $this->shipment($order, 'confirmed');
        $this->shipx(['taken_by_courier']);
        $this->actingAs(User::factory()->create());

        // MA-223: „Wysłane” by hand, then „Wróć do W realizacji” — the date and the number stay with the order.
        $this->patch('/panel/zamowienia/'.$order->number.'/status', ['status' => 'shipped', 'tracking_number' => self::TRACKING]);
        $this->patch('/panel/zamowienia/'.$order->number.'/status', ['status' => 'in_progress']);
        $this->assertNotNull($order->fresh()->shipped_at);

        $this->artisan('shipping:sync')->assertSuccessful();

        $this->assertSame(self::TRACKING, $order->fresh()->tracking_number);
        Mail::assertQueued(ParcelOnItsWay::class, 1);
    }

    public function test_a_problem_kasia_marked_after_sending_keeps_her_note(): void
    {
        $order = $this->order(['status' => OrderStatus::Problem, 'shipped_at' => now()->subDay(), 'problem_note' => 'pęknięty kubek']);
        $this->shipment($order, 'adopted_at_source_branch');
        $this->shipx(['out_for_delivery']);

        $this->artisan('shipping:sync')->assertSuccessful();

        $order->refresh();
        $this->assertSame([OrderStatus::Problem, 'pęknięty kubek'], [$order->status, $order->problem_note]);
        Mail::assertNotQueued(ParcelOnItsWay::class);
    }

    public function test_when_inpost_does_not_answer_nothing_changes_and_repeated_silence_raises_one_alert(): void
    {
        $order = $this->order();
        $this->shipment($order, 'confirmed');
        Http::fake([self::API.'shipments/777' => Http::sequence()
            ->pushFailedConnection()
            ->push(['error' => 'internal'], 500)
            ->pushFailedConnection()
            ->pushFailedConnection()]);

        $this->artisan('shipping:sync')->assertFailed();
        $this->artisan('shipping:sync')->assertFailed();
        Mail::assertNotSent(Alert::class);

        $this->artisan('shipping:sync')->assertFailed();
        $this->artisan('shipping:sync')->assertFailed();

        $this->assertSame(['confirmed', OrderStatus::InProgress], [Shipment::sole()->status, $order->fresh()->status]);
        Mail::assertSent(Alert::class, 1);
        Mail::assertNotQueued(ParcelOnItsWay::class);
    }

    public function test_only_parcels_still_on_their_way_and_younger_than_three_weeks_are_asked_about(): void
    {
        $this->shipment($this->order(), 'confirmed', 1);
        $this->shipment($this->order(['status' => OrderStatus::Completed]), 'delivered', 2);
        $this->shipment($this->order(['status' => OrderStatus::Problem]), 'returned_to_sender', 3);
        $this->travelTo(now()->subDays(22), fn () => $this->shipment($this->order(), 'confirmed', 4));
        Http::fake([self::API.'shipments/*' => Http::response(['status' => 'confirmed'])]);

        $this->artisan('shipping:sync')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === self::API.'shipments/1');
    }

    public function test_without_the_shipx_key_the_sync_does_nothing(): void
    {
        config(['services.inpost.shipx_token' => null]);
        $this->shipment($this->order(), 'confirmed');
        Http::fake();

        $this->artisan('shipping:sync')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_the_scheduler_runs_the_sync_every_quarter_of_an_hour(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'shipping:sync'));

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
    }

    public function test_the_panel_shows_where_the_parcel_is_in_plain_words(): void
    {
        $order = $this->order(['status' => OrderStatus::Shipped, 'shipped_at' => now()]);
        $this->shipment($order, 'ready_to_pickup');

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertSee('W InPost')
            ->assertSee('Czeka w paczkomacie');
    }

    /**
     * @param  list<string>  $statuses  what ShipX answers for shipment 777, one per request
     */
    private function shipx(array $statuses): void
    {
        $sequence = Http::sequence();

        foreach ($statuses as $status) {
            $sequence->push(['id' => 777, 'status' => $status, 'tracking_number' => self::TRACKING]);
        }

        Http::fake([self::API.'shipments/777' => $sequence]);
    }

    private function shipment(Order $order, string $status, int $shipxId = 777): Shipment
    {
        return Shipment::create([
            'order_id' => $order->id, 'shipx_id' => $shipxId, 'service' => Shipment::LOCKER, 'size' => 'medium',
            'target_point' => 'KRA01M', 'tracking_number' => self::TRACKING, 'status' => $status,
        ]);
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
            'locker_code' => 'KRA01M',
            'shipping_gross' => 1600,
            'total_gross' => 25500,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
            ...$attributes,
        ]);

        return tap($order)->update(['number' => 'MA-2026-'.(1000 + $order->id)]);
    }
}
