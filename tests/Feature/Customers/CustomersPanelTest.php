<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Checkout\Enums\ComplaintDecision;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Enums\WithdrawalScope;
use App\Modules\Checkout\Models\ComplaintAnswer;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\Withdrawal;
use App\Modules\Customers\Enums\PrivacyRequestType;
use App\Modules\Customers\Models\PrivacyRequest;
use App\Modules\Customers\Support\CustomerKey;
use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Firing\Enums\FiringRequestStatus;
use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Shared\Support\PersonalData;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CustomersPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_the_screen_is_the_owners_alone(): void
    {
        $this->order();
        $key = CustomerKey::of('ania@example.com');

        $this->get('/panel/klientki')->assertRedirect('/panel/logowanie');

        $this->actingAs(User::factory()->helper()->create());
        $this->get('/panel/klientki')->assertForbidden();
        $this->get('/panel/klientki/'.$key)->assertForbidden();
        $this->post('/panel/klientki/'.$key.'/kopia-danych')->assertForbidden();
        $this->delete('/panel/klientki/'.$key, ['confirm_email' => 'ania@example.com'])->assertForbidden();
        $this->get('/panel')->assertDontSee('Klientki');

        $this->actingAs(User::factory()->create())->get('/panel')->assertSee('Klientki');
    }

    public function test_one_person_gathers_her_records_from_every_module_by_address(): void
    {
        Carbon::setTestNow('2026-10-20 10:00');
        $this->order(['email' => 'Ania@Example.com']);
        Carbon::setTestNow('2026-10-25 10:00');
        $this->idea();
        $this->batch(['phone' => '+48 600 100 200']);
        Carbon::setTestNow('2026-10-22 10:00');
        $this->order(['name' => 'Basia Kowalska', 'email' => 'basia@example.com', 'phone' => '511 222 333']);
        $this->order(['name' => PersonalData::ERASED_NAME, 'email' => PersonalData::ERASED_EMAIL, 'phone' => '']);
        Carbon::setTestNow('2026-10-28 10:00');

        $this->actingAs(User::factory()->create())
            ->get('/panel/klientki')
            ->assertOk()
            ->assertSeeInOrder([
                'Anna Nowak', 'ania@example.com', '1 zamówienie · 1 zamówienie indywidualne · 1 wypał', 'ostatnio 25.10.2026',
                'Basia Kowalska', 'basia@example.com · 511 222 333', '1 zamówienie',
            ])
            ->assertDontSee(PersonalData::ERASED_NAME)
            ->assertSee('href="'.route('admin.customers.show', CustomerKey::of('ania@example.com')).'"', false)
            ->assertSee('Na razie żadnych.');

        $this->get('/panel/klientki?szukaj=KOWALSKA')->assertSee('basia@example.com')->assertDontSee('ania@example.com');
        $this->get('/panel/klientki?szukaj=ania@')->assertSee('ania@example.com')->assertDontSee('basia@example.com');
        $this->get('/panel/klientki?szukaj=600100200')->assertSee('ania@example.com')->assertDontSee('basia@example.com');
        $this->get('/panel/klientki?szukaj=Zosia')->assertSee('Nikogo nie znalazłam');
    }

    public function test_the_card_shows_everything_about_her_and_its_address_hides_hers(): void
    {
        $order = $this->order();
        $order->items()->create(['product_name' => 'Kubek z napisem', 'variant_label' => '300 ml', 'quantity' => 1, 'unit_price_gross' => 12900, 'custom_text' => 'Ania']);
        $this->idea(['description' => 'Kubki <b>na ślub</b>', 'status' => CustomOrderStatus::Quoting]);
        $this->batch();
        $key = CustomerKey::of('ania@example.com');

        $this->assertStringNotContainsString('ania', route('admin.customers.show', $key));

        $this->actingAs(User::factory()->create())
            ->get('/panel/klientki/'.$key)
            ->assertOk()
            ->assertSeeInOrder([
                'Anna Nowak', 'ania@example.com', '600 100 200',
                'Zamówienia i warsztaty', $order->number, 'Kubek z napisem (300 ml) × 1', '255,00 zł', 'Zakończone',
                'Zamówienia indywidualne', 'Co to ma być: Kubki z napisem', 'Kubki &lt;b&gt;na ślub&lt;/b&gt;',
                'Zgłoszenia do wypału', 'Wypał: Wypał biskwitowy',
                'Usuń jej dane',
            ], false)
            ->assertSee('Najpierw dokończ te sprawy')
            ->assertDontSee('name="confirm_email"', false);

        $this->get('/panel/klientki/'.str_repeat('0', 32))->assertNotFound();
    }

    public function test_the_copy_of_her_data_downloads_as_json_and_goes_to_the_log_without_her_address(): void
    {
        $order = $this->order(['shipping_address' => ['street' => 'Długa 1', 'postal_code' => '30-001', 'city' => 'Kraków']]);
        $order->items()->create(['product_name' => 'Kubek z napisem', 'variant_label' => '300 ml', 'quantity' => 1, 'unit_price_gross' => 12900, 'custom_text' => 'Ania']);
        $this->idea();
        $this->order(['email' => 'basia@example.com', 'name' => 'Basia']);

        $response = $this->actingAs(User::factory()->create(['name' => 'Kasia']))
            ->post('/panel/klientki/'.CustomerKey::of('ania@example.com').'/kopia-danych')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="mellowaura-dane-'.now()->format('Y-m-d').'.json"');

        $copy = json_decode($response->getContent(), true);
        $this->assertSame('ania@example.com', $copy['email']);
        $this->assertSame([$order->number], array_column($copy['orders'], 'number'));
        $this->assertSame('Długa 1', $copy['orders'][0]['shipping_address']['street']);
        $this->assertSame('Ania', $copy['orders'][0]['items'][0]['inscription']);
        $this->assertSame('Kubki dla świadków', $copy['custom_orders'][0]['description']);
        $this->assertStringContainsString('Kubki dla świadków', $response->getContent());

        $logged = PrivacyRequest::sole();
        $this->assertSame([PrivacyRequestType::Export, CustomerKey::of('ania@example.com'), 'Kasia'], [$logged->type, $logged->customer_key, $logged->performed_by]);
        $this->assertSame(1, $logged->summary['orders']);
        $this->assertStringNotContainsString('ania', json_encode($logged->getAttributes()));

        $this->get('/panel/klientki')->assertSeeInOrder(['Wykonane żądania RODO', 'Kopia danych', 'zamówienia: 1 · zamówienia indywidualne: 1', 'Kasia']);
    }

    public function test_nothing_is_erased_while_something_of_hers_is_still_open(): void
    {
        $open = [
            fn () => $this->order(['status' => OrderStatus::InProgress]),
            fn () => $this->order(['status' => OrderStatus::New, 'payment_status' => PaymentStatus::Pending, 'paid_at' => null]),
            fn () => $this->idea(['status' => CustomOrderStatus::Quoting]),
            fn () => $this->batch(['status' => FiringRequestStatus::Accepted]),
            fn () => Withdrawal::query()->create(['order_number' => 'MA-2026-1001', 'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'scope' => WithdrawalScope::Whole, 'submitted_at' => now()]),
            function () {
                $order = $this->order();
                $date = WorkshopDate::factory()->create(['starts_at' => now()->addWeek()]);
                WorkshopBooking::query()->create(['workshop_date_id' => $date->id, 'order_id' => $order->id, 'seats' => 1, 'status' => BookingStatus::Confirmed, 'expires_at' => now()]);
            },
        ];
        $owner = User::factory()->create();

        foreach ($open as $case) {
            WorkshopBooking::query()->delete();
            Withdrawal::query()->delete();
            Order::query()->delete();
            CustomOrderRequest::query()->delete();
            FiringRequest::query()->delete();
            $case();

            $this->actingAs($owner)
                ->delete('/panel/klientki/'.CustomerKey::of('ania@example.com'), ['confirm_email' => 'ania@example.com'])
                ->assertRedirect(route('admin.customers.show', CustomerKey::of('ania@example.com')))
                ->assertSessionHas('panel_status', 'Najpierw dokończ sprawy z listy — do nich potrzebny jest jeszcze jej adres.');

            $this->assertSame(0, PrivacyRequest::count());
            $this->get('/panel/klientki')->assertSee('ania@example.com');
        }
    }

    public function test_the_address_typed_again_must_be_hers(): void
    {
        $this->order();

        $this->actingAs(User::factory()->create())
            ->from('/panel/klientki/'.CustomerKey::of('ania@example.com'))
            ->delete('/panel/klientki/'.CustomerKey::of('ania@example.com'), ['confirm_email' => 'basia@example.com'])
            ->assertRedirect('/panel/klientki/'.CustomerKey::of('ania@example.com'))
            ->assertSessionHasErrors(['confirm_email' => 'Wpisz dokładnie adres z tej karty — to zabezpieczenie przed usunięciem nie tej osoby.']);

        $this->assertSame('ania@example.com', Order::sole()->email);
    }

    public function test_erasing_keeps_the_paid_sale_for_the_books_and_forgets_the_rest(): void
    {
        $paid = $this->order(['invoice_nip' => '6762460000', 'note' => 'Proszę zapakować na prezent', 'shipping_address' => ['street' => 'Długa 1', 'postal_code' => '30-001', 'city' => 'Kraków'], 'locker_code' => 'KRA01M']);
        $mug = $paid->items()->create(['product_name' => 'Kubek z napisem', 'variant_label' => '300 ml', 'quantity' => 1, 'unit_price_gross' => 12900, 'custom_text' => 'Ania']);
        $gift = $paid->items()->create(['product_name' => 'Voucher', 'variant_label' => '200 zł', 'quantity' => 1, 'unit_price_gross' => 20000, 'recipient_name' => 'Zosia', 'sender_name' => 'Ania', 'dedication' => 'Wszystkiego dobrego']);
        $voucher = Voucher::factory()->create(['order_item_id' => $gift->id, 'recipient_name' => 'Zosia', 'sender_name' => 'Ania', 'dedication' => 'Wszystkiego dobrego']);
        $failed = $this->order(['email' => 'ANIA@example.com', 'status' => OrderStatus::New, 'payment_status' => PaymentStatus::Failed, 'paid_at' => null, 'invoice_nip' => '6762460000']);
        $idea = $this->idea(['phone' => '600 100 200', 'notes' => 'Dzwonić po 17', 'status' => CustomOrderStatus::Shipped]);
        $photo = $idea->addMedia(UploadedFile::fake()->image('kubek.webp', 40, 40))->toMediaCollection('photos');
        $batch = $this->batch(['status' => FiringRequestStatus::Collected]);
        $withdrawal = Withdrawal::query()->create(['order_id' => $paid->id, 'order_number' => $paid->number, 'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'scope' => WithdrawalScope::Whole, 'submitted_at' => now(), 'handled_at' => now()]);
        $complaint = ComplaintAnswer::query()->create(['order_number' => $paid->number, 'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'received_on' => now(), 'decision' => ComplaintDecision::Accepted, 'letter' => 'Pani Anno, …']);
        $other = $this->order(['name' => 'Basia', 'email' => 'basia@example.com']);
        app(FailedJobProviderInterface::class)->log('database', 'mail', json_encode(['uuid' => (string) Str::uuid(), 'mail' => ['description' => 'Potwierdzenie zamówienia', 'to' => ['Ania@example.com']]]), new RuntimeException('Postmark nie odpowiada'));
        app(FailedJobProviderInterface::class)->log('database', 'mail', json_encode(['uuid' => (string) Str::uuid(), 'mail' => ['description' => 'Potwierdzenie zamówienia', 'to' => ['basia@example.com']]]), new RuntimeException('Postmark nie odpowiada'));

        $this->actingAs(User::factory()->create(['name' => 'Kasia']))
            ->delete('/panel/klientki/'.CustomerKey::of('ania@example.com'), ['confirm_email' => ' Ania@Example.com '])
            ->assertRedirect('/panel/klientki')
            ->assertSessionHas('panel_status', 'Dane usunięte. Zostały tylko opłacone zamówienia do księgowości oraz odstąpienia i reklamacje — bez adresu e-mail.');

        $paid->refresh();
        $this->assertSame(['Anna Nowak', '6762460000', 25500, PersonalData::ERASED_EMAIL, '', null, null, null], [$paid->name, $paid->invoice_nip, $paid->total_gross, $paid->email, $paid->phone, $paid->shipping_address, $paid->locker_code, $paid->note]);
        $this->assertSame(['Kubek z napisem', 12900, null], [$mug->fresh()->product_name, $mug->fresh()->unit_price_gross, $mug->fresh()->custom_text]);
        $this->assertSame([null, null, null], [$gift->fresh()->recipient_name, $gift->fresh()->sender_name, $gift->fresh()->dedication]);
        $this->assertSame([$voucher->code, null, null, null], [$voucher->fresh()->code, $voucher->fresh()->recipient_name, $voucher->fresh()->sender_name, $voucher->fresh()->dedication]);

        $failed->refresh();
        $this->assertSame([PersonalData::ERASED_NAME, PersonalData::ERASED_EMAIL, null], [$failed->name, $failed->email, $failed->invoice_nip]);

        $idea->refresh();
        $this->assertSame([PersonalData::ERASED_NAME, PersonalData::ERASED_EMAIL, null, '', null, CustomOrderStatus::Shipped], [$idea->name, $idea->email, $idea->phone, $idea->description, $idea->notes, $idea->status]);
        $this->assertCount(0, $idea->getMedia('photos'));
        Storage::disk('local')->assertMissing($photo->getPathRelativeToRoot());

        $batch->refresh();
        $this->assertSame([PersonalData::ERASED_NAME, PersonalData::ERASED_EMAIL, null, null, 'Wypał biskwitowy'], [$batch->name, $batch->email, $batch->phone, $batch->message, $batch->service_label]);

        $this->assertSame(['Anna Nowak', PersonalData::ERASED_EMAIL], [$withdrawal->fresh()->name, $withdrawal->fresh()->email]);
        $this->assertSame(['Pani Anno, …', PersonalData::ERASED_EMAIL], [$complaint->fresh()->letter, $complaint->fresh()->email]);
        $this->assertSame('basia@example.com', $other->fresh()->email);
        $this->assertSame([['basia@example.com']], collect(app(FailedJobProviderInterface::class)->all())->map(fn ($job) => json_decode($job->payload, true)['mail']['to'])->all());

        $logged = PrivacyRequest::sole();
        $this->assertSame([PrivacyRequestType::Erasure, CustomerKey::of('ania@example.com'), 'Kasia'], [$logged->type, $logged->customer_key, $logged->performed_by]);
        // assertEquals: MySQL keeps a JSON column's keys in its own order.
        $this->assertEquals(['vouchers' => 1, 'shipments' => 0, 'orders' => 2, 'paid_orders' => 1, 'withdrawals' => 1, 'complaints' => 1, 'firing_requests' => 1, 'failed_mails' => 1, 'custom_orders' => 1], $logged->summary);

        $this->get('/panel/klientki')
            ->assertDontSee('ania@example.com')
            ->assertSee('basia@example.com')
            ->assertSeeInOrder(['Usunięcie danych', 'zamówienia: 2 · opłacone, zostają do księgowości: 1', 'Kasia']);
        $this->get('/panel/klientki/'.CustomerKey::of('ania@example.com'))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = []): Order
    {
        $order = Order::create([
            'status' => OrderStatus::Completed,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600 100 200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
            'shipping_gross' => 1600,
            'total_gross' => 25500,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
            ...$attributes,
        ]);

        return tap($order)->update(['number' => 'MA-2026-'.(1000 + $order->id)]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function idea(array $attributes = []): CustomOrderRequest
    {
        return CustomOrderRequest::query()->create([
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'kind' => BriefKind::Mugs,
            'description' => 'Kubki dla świadków',
            'status' => CustomOrderStatus::Declined,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function batch(array $attributes = []): FiringRequest
    {
        return FiringRequest::query()->create([
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'service_code' => 'bisque_firing',
            'service_label' => 'Wypał biskwitowy',
            'unit' => 'kg',
            'quantity' => '2.5',
            'unit_price' => 3000,
            'message' => 'Sześć miseczek',
            'status' => FiringRequestStatus::Collected,
            ...$attributes,
        ]);
    }
}
