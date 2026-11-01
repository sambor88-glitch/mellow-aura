<?php

namespace Tests\Feature\Shipping;

use App\Models\User;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Models\Setting;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * „Nadaj w InPost” in the panel (MA-132), against a faked ShipX: nothing here talks to InPost.
 */
class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://sandbox-api-shipx-pl.easypack24.net/v1/';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        config(['services.inpost.shipx_token' => 'shipx-token', 'services.inpost.shipx_organization_id' => '1234', 'services.inpost.shipx_sandbox' => true]);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
            ['code' => 'courier', 'label' => 'Kurier InPost', 'price_gross' => 1900],
            ['code' => 'studio_pickup', 'label' => 'Odbiór w pracowni', 'price_gross' => 0],
        ]]);
    }

    public function test_without_the_shipx_key_the_panel_says_how_to_send_meanwhile(): void
    {
        config(['services.inpost.shipx_token' => null]);
        $order = $this->order();

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertOk()
            ->assertSee('Nadawanie z panelu włączy się')
            ->assertDontSee('Nadaj w InPost');

        $this->actingAs(User::factory()->create())
            ->post('/panel/zamowienia/'.$order->number.'/przesylka', ['size' => 'medium', 'target_point' => 'KRA01M'])
            ->assertNotFound();
    }

    public function test_a_parcel_goes_to_the_customers_locker_in_one_click(): void
    {
        Setting::create(['key' => 'parcel_locker_code', 'value' => 'KRA99M']);
        Http::fake([self::API.'organizations/1234/shipments' => Http::response(['id' => 777, 'status' => 'created', 'tracking_number' => null], 201)]);
        $order = $this->order(['locker_code' => 'KRA01M']);

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertSee('Nadaj w InPost')
            ->assertSee('value="KRA01M"', false)
            ->assertSee('Wybrany przez klienta przy zakupie');

        $this->actingAs(User::factory()->create())
            ->post('/panel/zamowienia/'.$order->number.'/przesylka', ['size' => 'small', 'target_point' => ' kra01m '])
            ->assertRedirect('/panel/zamowienia/'.$order->number)
            ->assertSessionHas('panel_status', 'Przesyłka nadana w InPost. Pobierz etykietę i naklej ją na paczkę.');

        Http::assertSent(fn (Request $request) => $request->url() === self::API.'organizations/1234/shipments'
            && $request->hasHeader('Authorization', 'Bearer shipx-token')
            && $request['service'] === 'inpost_locker_standard'
            && $request['parcels'] === ['template' => 'small']
            && $request['custom_attributes'] === ['target_point' => 'KRA01M', 'sending_method' => 'parcel_locker', 'dropoff_point' => 'KRA99M']
            && $request['receiver'] === ['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'ania@example.com', 'phone' => '600100200']
            && $request['reference'] === $order->number);

        $shipment = Shipment::sole();
        $this->assertSame([777, 'small', 'KRA01M', 'created'], [$shipment->shipx_id, $shipment->size, $shipment->target_point, $shipment->status]);
        // Sending the parcel is not telling the customer: the order waits for Kasia's „Wysłane”.
        $this->assertSame(OrderStatus::InProgress, $order->fresh()->status);
    }

    public function test_the_label_comes_once_inpost_has_bought_it_and_brings_the_tracking_number(): void
    {
        $order = $this->order(['locker_code' => 'KRA01M']);
        Shipment::create(['order_id' => $order->id, 'shipx_id' => 777, 'service' => Shipment::LOCKER, 'size' => 'medium', 'target_point' => 'KRA01M', 'status' => 'created']);
        Http::fake([
            self::API.'shipments/777' => Http::sequence()
                ->push(['id' => 777, 'status' => 'offer_selected', 'tracking_number' => null])
                ->push(['id' => 777, 'status' => 'confirmed', 'tracking_number' => '520000011223344556677']),
            self::API.'shipments/777/label*' => Http::response('%PDF-1.4 label', 200, ['Content-Type' => 'application/pdf']),
        ]);
        $panel = $this->actingAs(User::factory()->create());

        $panel->get('/panel/zamowienia/'.$order->number.'/etykieta')
            ->assertRedirect('/panel/zamowienia/'.$order->number)
            ->assertSessionHas('panel_status', 'InPost jeszcze przygotowuje etykietę. Spróbuj za minutę.');

        $panel->get('/panel/zamowienia/'.$order->number.'/etykieta')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="etykieta-'.$order->number.'.pdf"');

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::API.'shipments/777/label') && $request['format'] === 'pdf' && $request['type'] === 'A6');
        $this->assertSame('520000011223344556677', Shipment::sole()->tracking_number);

        // One more click and the customer hears about it, with the number.
        $panel->get('/panel/zamowienia/'.$order->number)
            ->assertSee('520000011223344556677')
            ->assertSee('<input type="hidden" name="tracking_number" value="520000011223344556677">', false)
            ->assertDontSee('Nadaj w InPost');
    }

    public function test_a_locker_inpost_does_not_know_is_said_in_a_sentence_and_nothing_is_saved(): void
    {
        Http::fake([self::API.'organizations/1234/shipments' => Http::response([
            'status' => 400, 'error' => 'validation_failed', 'message' => 'There are some validation errors.',
            'details' => ['custom_attributes' => [['target_point' => ['does_not_exist']]]],
        ], 400)]);
        $order = $this->order(['locker_code' => 'XYZ00M']);

        $this->actingAs(User::factory()->create())
            ->from('/panel/zamowienia/'.$order->number)
            ->followingRedirects()
            ->post('/panel/zamowienia/'.$order->number.'/przesylka', ['size' => 'medium', 'target_point' => 'XYZ00M'])
            ->assertOk()
            ->assertSee('InPost nie zna paczkomatu XYZ00M');

        $this->assertSame(0, Shipment::count());
    }

    public function test_a_courier_parcel_takes_the_address_and_a_weight_written_with_a_comma(): void
    {
        Http::fake([self::API.'organizations/1234/shipments' => Http::response(['id' => 888, 'status' => 'created'], 201)]);
        $order = $this->order(['shipping_method' => 'courier', 'shipping_address' => ['street' => 'Długa 12/3', 'postal_code' => '30-001', 'city' => 'Kraków']]);

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertSee('Waga w kg');

        $this->actingAs(User::factory()->create())
            ->post('/panel/zamowienia/'.$order->number.'/przesylka', ['size' => 'large', 'weight' => '1,5'])
            ->assertRedirect('/panel/zamowienia/'.$order->number);

        Http::assertSent(fn (Request $request) => $request['service'] === 'inpost_courier_standard'
            && $request['receiver']['address'] === ['street' => 'Długa', 'building_number' => '12/3', 'city' => 'Kraków', 'post_code' => '30-001', 'country_code' => 'PL']
            && $request['parcels'] === [['dimensions' => ['length' => 640, 'width' => 380, 'height' => 410, 'unit' => 'mm'], 'weight' => ['amount' => 1.5, 'unit' => 'kg']]]
            && ! isset($request['custom_attributes']['target_point']));
        $this->assertSame(Shipment::COURIER, Shipment::sole()->service);
    }

    public function test_a_locker_and_a_size_are_asked_for_before_anything_goes_to_inpost(): void
    {
        Http::fake();
        $order = $this->order();

        $this->actingAs(User::factory()->create())
            ->post('/panel/zamowienia/'.$order->number.'/przesylka', ['size' => 'huge', 'target_point' => ''])
            ->assertSessionHasErrors(['size' => 'Wybierz rozmiar paczki', 'target_point' => 'Wpisz kod paczkomatu klienta, np. KRA01M']);

        Http::assertNothingSent();
    }

    public function test_pickup_at_the_studio_and_unpaid_orders_have_nothing_to_send(): void
    {
        $pickup = $this->order(['shipping_method' => 'studio_pickup']);
        $unpaid = $this->order(['payment_status' => PaymentStatus::Pending, 'paid_at' => null, 'status' => OrderStatus::New]);

        foreach ([$pickup, $unpaid] as $order) {
            $this->actingAs(User::factory()->create())
                ->get('/panel/zamowienia/'.$order->number)
                ->assertOk()
                ->assertDontSee('Nadaj w InPost');

            $this->actingAs(User::factory()->create())
                ->post('/panel/zamowienia/'.$order->number.'/przesylka', ['size' => 'medium', 'target_point' => 'KRA01M'])
                ->assertNotFound();
        }
    }

    public function test_a_helper_who_packs_may_send_the_parcel(): void
    {
        Http::fake([self::API.'organizations/1234/shipments' => Http::response(['id' => 999, 'status' => 'created'], 201)]);
        $order = $this->order(['locker_code' => 'KRA01M']);

        $this->actingAs(User::factory()->create(['role' => 'helper']))
            ->post('/panel/zamowienia/'.$order->number.'/przesylka', ['size' => 'medium', 'target_point' => 'KRA01M'])
            ->assertRedirect('/panel/zamowienia/'.$order->number);

        $this->assertSame(1, Shipment::count());
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
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
            'shipping_gross' => 1600,
            'total_gross' => 25500,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
            ...$attributes,
        ]);

        // PlaceOrder numbers an order after saving it; the test does the same.
        return tap($order)->update(['number' => 'MA-2026-'.(1000 + $order->id)]);
    }
}
