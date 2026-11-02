<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Enums\Role;
use App\Modules\Admin\Jobs\SendPanelPush;
use App\Modules\Admin\Models\PushSubscription;
use App\Modules\Admin\Support\Push\PanelNotice;
use App\Modules\Admin\Support\Push\PushSender;
use App\Modules\Admin\Support\Push\WebPush;
use App\Modules\Admin\Support\Push\WebPushSender;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Models\Order;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Notifications on the panel's phones (MA-345): the switch on „Powiadomienia”, the queue job and what each module
 * sends. The push services are never called — a fake sender records what would go and answers for each device.
 */
class PanelPushTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{ids: list<int>, payload: array<string, string>}> */
    public array $sent = [];

    /** What the fake push service answers, per subscription id; SENT when not set. */
    public array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['services.webpush.public_key' => 'BPublicKeyForTests', 'services.webpush.private_key' => 'private-key-for-tests']);

        $test = $this;
        $this->app->instance(PushSender::class, new class($test) implements PushSender
        {
            public function __construct(private PanelPushTest $test) {}

            public function send(Collection $subscriptions, array $payload): array
            {
                $this->test->sent[] = ['ids' => $subscriptions->pluck('id')->all(), 'payload' => $payload];

                return $subscriptions->mapWithKeys(fn (PushSubscription $subscription) => [
                    $subscription->id => $this->test->answers[$subscription->id] ?? PushSender::SENT,
                ])->all();
            }
        });
    }

    public function test_the_screen_turns_the_notifications_on_for_this_phone(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->get('/panel/powiadomienia')
            ->assertOk()
            ->assertSee('Włącz powiadomienia na tym telefonie')
            ->assertSee('BPublicKeyForTests')
            ->assertSee('Do ekranu początkowego')
            ->assertSee('Żaden telefon jeszcze nie dostaje powiadomień.');

        $this->actingAs($owner)->get('/panel')->assertSee('href="'.route('admin.push.index').'"', false);
    }

    public function test_without_the_keys_the_screen_says_they_wait_and_nothing_is_queued(): void
    {
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
        Queue::fake();
        $owner = User::factory()->create();
        $this->subscribe($owner);

        $this->actingAs($owner)->get('/panel/powiadomienia')
            ->assertOk()
            ->assertSee('Powiadomienia czekają jeszcze na klucze na serwerze')
            ->assertDontSee('Włącz powiadomienia na tym telefonie');

        $this->actingAs($owner)->postJson('/panel/powiadomienia/proba')->assertOk();
        Queue::assertNothingPushed();
    }

    public function test_a_phone_is_saved_once_and_the_same_browser_moves_to_the_account_signed_in(): void
    {
        $owner = User::factory()->create();
        $helper = User::factory()->create(['role' => Role::Helper]);
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

        $this->actingAs($owner)->withHeader('User-Agent', $iphone)->postJson('/panel/powiadomienia', $this->browser())
            ->assertCreated()
            ->assertJson(['device' => 'iPhone']);
        $this->actingAs($owner)->withHeader('User-Agent', $iphone)->postJson('/panel/powiadomienia', $this->browser(['keys' => ['p256dh' => 'renewed', 'auth' => 'renewed-auth']]))
            ->assertOk();

        $subscription = PushSubscription::sole();
        $this->assertSame([$owner->id, 'renewed', 'renewed-auth', 'aes128gcm'], [$subscription->user_id, $subscription->public_key, $subscription->auth_token, $subscription->content_encoding]);

        $this->actingAs($helper)->postJson('/panel/powiadomienia', $this->browser())->assertOk();
        $this->assertSame($helper->id, PushSubscription::sole()->user_id);
    }

    public function test_only_a_push_service_address_with_its_keys_is_taken(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->postJson('/panel/powiadomienia', $this->browser(['endpoint' => 'http://push.example/abc']))->assertJsonValidationErrors('endpoint');
        $this->actingAs($owner)->postJson('/panel/powiadomienia', $this->browser(['keys' => ['p256dh' => '']]))->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
        $this->actingAs($owner)->postJson('/panel/powiadomienia', $this->browser(['contentEncoding' => 'plain']))->assertJsonValidationErrors('contentEncoding');
        $this->assertDatabaseCount('push_subscriptions', 0);

        auth()->logout();
        $this->postJson('/panel/powiadomienia', $this->browser())->assertUnauthorized();
    }

    public function test_turning_off_and_removing_touch_only_the_own_devices(): void
    {
        $owner = User::factory()->create();
        $helper = User::factory()->create(['role' => Role::Helper]);
        $phone = $this->subscribe($owner, 'https://push.example/owner-phone');
        $laptop = $this->subscribe($owner, 'https://push.example/owner-laptop', 'Mac · Chrome');
        $helpersPhone = $this->subscribe($helper, 'https://push.example/helper-phone');

        $this->actingAs($helper)->postJson('/panel/powiadomienia/wylacz', ['endpoint' => 'https://push.example/owner-phone'])->assertOk();
        $this->actingAs($helper)->delete('/panel/powiadomienia/'.$laptop->id)->assertNotFound();
        $this->assertModelExists($phone);
        $this->assertModelExists($laptop);

        $this->actingAs($owner)->postJson('/panel/powiadomienia/wylacz', ['endpoint' => 'https://push.example/owner-phone'])->assertOk();
        $this->assertModelMissing($phone);

        $this->actingAs($owner)->delete('/panel/powiadomienia/'.$laptop->id)
            ->assertRedirect('/panel/powiadomienia')
            ->assertSessionHas('panel_status', 'Usunięte — „Mac · Chrome” nie dostanie już powiadomień.');
        $this->assertModelMissing($laptop);
        $this->assertModelExists($helpersPhone);
    }

    public function test_the_trial_rings_only_the_account_that_asked(): void
    {
        $owner = User::factory()->create();
        $mine = $this->subscribe($owner);
        $this->subscribe(User::factory()->create(), 'https://push.example/someone-else');

        $this->actingAs($owner)->postJson('/panel/powiadomienia/proba')->assertOk();

        $this->assertCount(1, $this->sent);
        $this->assertSame([$mine->id], $this->sent[0]['ids']);
        $this->assertSame('Próbne powiadomienie z panelu', $this->sent[0]['payload']['title']);
        $this->assertNotNull($mine->fresh()->last_sent_at);
    }

    public function test_a_device_that_is_gone_is_removed_and_one_that_failed_is_tried_again_alone(): void
    {
        $owner = User::factory()->create();
        $ok = $this->subscribe($owner, 'https://push.example/ok');
        $gone = $this->subscribe($owner, 'https://push.example/gone');
        $busy = $this->subscribe($owner, 'https://push.example/busy');
        $this->answers = [$gone->id => PushSender::GONE, $busy->id => PushSender::FAILED];

        Queue::fake();
        (new SendPanelPush($this->notice()))->handle(app(PushSender::class));

        $this->assertModelMissing($gone);
        $this->assertNotNull($ok->fresh()->last_sent_at);
        $this->assertNull($busy->fresh()->last_sent_at);
        Queue::assertPushed(SendPanelPush::class, fn (SendPanelPush $job) => $job->subscriptionIds === [$busy->id] && $job->round === 2);

        // The last round only logs: Kasia has the same news by e-mail.
        Queue::fake();
        (new SendPanelPush($this->notice(), [$busy->id], 3))->handle(app(PushSender::class));
        Queue::assertNothingPushed();
        $this->assertSame([$busy->id], end($this->sent)['ids']);
    }

    public function test_a_helper_hears_about_orders_and_nothing_else(): void
    {
        $owner = User::factory()->create();
        $helper = User::factory()->create(['role' => Role::Helper]);
        $ownersPhone = $this->subscribe($owner, 'https://push.example/owner');
        $helpersPhone = $this->subscribe($helper, 'https://push.example/helper');

        SendPanelPush::dispatch($this->notice(route: 'admin.orders.show'));
        SendPanelPush::dispatch($this->notice(route: 'admin.custom-orders.index'));

        $this->assertEqualsCanonicalizing([$ownersPhone->id, $helpersPhone->id], $this->sent[0]['ids']);
        $this->assertSame([$ownersPhone->id], $this->sent[1]['ids']);

        $this->actingAs($helper)->get('/panel/powiadomienia')->assertOk()->assertSee('nowe opłacone zamówienie do spakowania');
    }

    public function test_a_paid_order_rings_with_its_number_and_amount_and_no_customer_data(): void
    {
        Mail::fake();
        Setting::create(['key' => 'shipping_methods', 'value' => [['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600]]]);
        $phone = $this->subscribe(User::factory()->create());
        $product = Product::factory()->create(['name' => 'Wazony', 'slug' => 'wazony', 'category_id' => Category::factory()->create()->id, 'is_published' => true, 'stamp_enabled' => false]);
        $vase = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => 3]);

        $this->postJson('/koszyk', ['variant_id' => $vase->id]);
        $this->post('/zamowienie', [
            'phone' => '+48 600 100 200', 'email' => 'ania@example.com', 'name' => 'Anna Nowak',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
            'payment_method' => 'blik', 'blik_code' => '123456', 'accept_terms' => '1', 'expected_total' => 25500,
        ])->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::sole();
        $this->assertCount(1, $this->sent);
        $this->assertSame([$phone->id], $this->sent[0]['ids']);
        $this->assertSame([
            'title' => 'Nowe zamówienie '.$order->number.' · 255 zł',
            'body' => 'Opłacone — czeka na spakowanie.',
            'url' => route('admin.orders.show', $order),
            'tag' => 'order-'.$order->id,
        ], $this->sent[0]['payload']);
        $this->assertStringNotContainsString('Anna', json_encode($this->sent[0]['payload']));
    }

    public function test_a_paid_workshop_booking_rings_with_its_day_and_seats(): void
    {
        Mail::fake();
        $this->seed(SettingsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Warsaw'));
        $this->subscribe(User::factory()->create());
        $date = WorkshopDate::factory()->create(['workshop_code' => 'hand_building', 'starts_at' => '2026-10-31 11:00', 'ends_at' => '2026-10-31 13:00', 'seats' => 3]);

        $this->post(route('workshops.booking.store', $date), [
            'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'phone' => '600 100 200', 'seats' => 2,
            'payment_method' => 'blik', 'blik_code' => '123456', 'expected_total' => 44000, 'accept_terms' => '1',
        ])->assertRedirect(route('workshops.booking.confirmation'));

        $booking = WorkshopBooking::sole();
        // One notification: the booking's, not the shop's „Nowe zamówienie”.
        $this->assertCount(1, $this->sent);
        $this->assertSame([
            'title' => 'Nowy zapis na warsztat · 440 zł',
            'body' => 'sob. 31 października, 11:00 – 13:00 · 2 osoby',
            'url' => route('admin.workshops.dates.bookings', $date),
            'tag' => 'booking-'.$booking->id,
        ], $this->sent[0]['payload']);
    }

    public function test_a_new_custom_order_idea_rings_with_its_kind(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(SettingsSeeder::class);
        $this->subscribe(User::factory()->create());

        $this->post('/zamowienia-indywidualne', [
            'name' => 'Ola', 'email' => 'ola@example.com', 'phone' => '', 'kind' => 'tableware', 'quantity' => '',
            'needed_by' => '', 'budget' => '', 'description' => 'Serwis na 12 osób, szkliwo jak morze', 'website' => '',
        ])->assertRedirect();

        $idea = CustomOrderRequest::sole();
        $this->assertCount(1, $this->sent);
        $this->assertSame([
            'title' => 'Nowy pomysł na zamówienie indywidualne',
            'body' => 'Serwis obiadowy albo do kawy — czeka na wycenę.',
            'url' => route('admin.custom-orders.index').'#pomysl-'.$idea->id,
            'tag' => 'custom-order-'.$idea->id,
        ], $this->sent[0]['payload']);
    }

    public function test_the_worker_shows_a_notification_and_opens_only_panel_addresses(): void
    {
        $script = $this->get('/panel/sw.js')->assertOk()->getContent();

        $this->assertStringContainsString("addEventListener('push'", $script);
        $this->assertStringContainsString('showNotification(', $script);
        $this->assertStringContainsString("addEventListener('notificationclick'", $script);
        $this->assertStringContainsString("target.pathname.startsWith('\\/panel')", $script);
        // The notification is shown, never kept.
        $this->assertStringNotContainsString('cache.put(', $script);
    }

    public function test_the_sender_signs_and_encrypts_for_each_device_and_reads_the_answers(): void
    {
        $keys = WebPush::createKeys();
        config(['services.webpush.public_key' => $keys['public'], 'services.webpush.private_key' => $keys['private']]);
        Http::preventStrayRequests();
        Http::fake([
            'push.example/ok' => Http::response(null, 201),
            'push.example/gone' => Http::response(null, 410),
            'push.example/busy' => Http::response('busy', 503),
        ]);
        $owner = User::factory()->create();
        $devices = collect(['ok', 'gone', 'busy'])->map(function (string $name) use ($owner) {
            $device = openssl_pkey_get_details(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']))['ec'];
            $subscription = $this->subscribe($owner, 'https://push.example/'.$name);
            $subscription->update([
                'public_key' => WebPush::encode("\x04".str_pad($device['x'], 32, "\0", STR_PAD_LEFT).str_pad($device['y'], 32, "\0", STR_PAD_LEFT)),
                'auth_token' => WebPush::encode(random_bytes(16)),
            ]);

            return $subscription;
        });

        $outcomes = (new WebPushSender)->send($devices, $this->notice()->payload());

        $this->assertSame([$devices[0]->id => PushSender::SENT, $devices[1]->id => PushSender::GONE, $devices[2]->id => PushSender::FAILED], $outcomes);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://push.example/ok'
            && $request->hasHeader('Content-Encoding', 'aes128gcm')
            && $request->hasHeader('Urgency', 'high')
            && str_starts_with($request->header('Authorization')[0], 'vapid t=')
            && str_ends_with($request->header('Authorization')[0], ', k='.$keys['public'])
            // Encrypted: nothing of the message is readable on the way.
            && ! str_contains($request->body(), 'zamówienie'));
    }

    public function test_the_command_prints_a_new_key_pair_for_the_environment(): void
    {
        config(['services.webpush.public_key' => null]);

        $this->artisan('admin:vapid-keys')
            ->expectsOutputToContain('VAPID_PUBLIC_KEY=B')
            ->expectsOutputToContain('VAPID_PRIVATE_KEY=')
            ->expectsOutputToContain('Forge → site → Environment')
            ->assertSuccessful();
    }

    private function notice(string $route = 'admin.orders.show'): PanelNotice
    {
        return new PanelNotice('Nowe zamówienie MA-2026-1001 · 255 zł', 'Opłacone — czeka na spakowanie.', url('/panel/zamowienia/MA-2026-1001'), $route, 'order-1');
    }

    private function subscribe(User $user, string $endpoint = 'https://push.example/phone', string $device = 'iPhone'): PushSubscription
    {
        return PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hash($endpoint),
            'public_key' => 'p256dh-key',
            'auth_token' => 'auth-secret',
            'device' => $device,
        ]);
    }

    /**
     * What PushSubscription.toJSON() gives in the browser.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function browser(array $overrides = []): array
    {
        return [
            'endpoint' => 'https://web.push.apple.com/QGuQyavXutnMH',
            'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA', 'auth' => 'tBHItJI5svbpez7KI4CCXg'],
            'contentEncoding' => 'aes128gcm',
            ...$overrides,
        ];
    }
}
